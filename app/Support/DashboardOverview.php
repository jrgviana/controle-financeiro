<?php

namespace App\Support;

use App\Livewire\DailyExpenses;
use App\Models\CreditCard;
use App\Models\CreditCardInvoicePayment;
use App\Models\CreditCardPurchase;
use App\Models\DailyExpense;
use App\Models\ReceivedIncome;
use App\Models\RecurringBill;
use App\Models\ScheduledBill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardOverview
{
    private const CHART_COLORS = [
        '#5867d8', '#10a59b', '#f0a13d', '#d97291',
        '#7588c8', '#8a70cc', '#539eb7', '#8b9caf',
    ];

    /**
     * Reúne dados já confirmados e previsões de forma separada.
     *
     * Entradas = recebimentos reais. Saídas = gastos pagos + pagamentos
     * de fatura. Transferências não são receitas nem despesas.
     * Compras no cartão não são descontadas do banco até pagar a fatura.
     * Recorrências sem competência confirmada são só estimativas.
     */
    public function build(int $userId, CarbonImmutable $month): array
    {
        $month = $month->startOfMonth();
        $from = $month->toDateString();
        $through = $month->endOfMonth()->toDateString();
        $today = CarbonImmutable::today();
        $agenda = app(PaymentAgenda::class)->forMonth($userId, $month->format('Y-m'));

        $trendStart = $month->subMonths(5)->toDateString();

        $incomes = ReceivedIncome::query()
            ->where('user_id', $userId)
            ->whereBetween('received_on', [$trendStart, $through])
            ->get(['id', 'received_on', 'category', 'amount']);
        $expenses = DailyExpense::query()
            ->where('user_id', $userId)
            ->whereBetween('paid_on', [$trendStart, $through])
            ->get(['id', 'paid_on', 'category', 'amount']);
        $cardPayments = CreditCardInvoicePayment::query()
            ->where('user_id', $userId)
            ->whereBetween('paid_on', [$trendStart, $through])
            ->get(['id', 'paid_on', 'amount']);

        $incomeMonthly = $this->groupByMonth($incomes, 'received_on');
        $expensesMonthly = $this->groupByMonth($expenses, 'paid_on');
        $cardPaymentsMonthly = $this->groupByMonth($cardPayments, 'paid_on');

        $trend = collect(range(5, 0))->map(function (int $ago) use ($month, $incomeMonthly, $expensesMonthly, $cardPaymentsMonthly): array {
            $date = $month->subMonths($ago);
            $period = $date->format('Y-m');
            $income = $incomeMonthly[$period] ?? 0;
            $paid = ($expensesMonthly[$period] ?? 0) + ($cardPaymentsMonthly[$period] ?? 0);

            return [
                'period' => $period,
                'label' => ucfirst($date->locale('pt_BR')->translatedFormat('M')),
                'income_cents' => $income,
                'paid_cents' => $paid,
            ];
        });

        $maxBar = max(1, ...$trend->flatMap(
            fn ($entry) => [$entry['income_cents'], $entry['paid_cents']]
        )->all());

        $trend = $trend->map(fn ($entry) => [
            ...$entry,
            'income_pct' => (int) round(100 * $entry['income_cents'] / $maxBar),
            'paid_pct' => (int) round(100 * $entry['paid_cents'] / $maxBar),
        ]);

        $monthExpenses = $expenses->filter(
            fn ($expense) => $expense->paid_on->format('Y-m') === $month->format('Y-m')
        );
        $categories = $this->spendingCategories($monthExpenses);

        // Parcelas futuras compõem dívida de cartão, mas não saída financeira
        // do mês. Limite e saldo em conta também nunca são somados.
        $cardLedger = app(CreditCardLedger::class);
        $cards = CreditCard::query()->where('user_id', $userId)
            ->orderByDesc('active')->orderBy('name')->get()
            ->map(function (CreditCard $card) use ($cardLedger): array {
                $debt = $cardLedger->totalDebt($card);
                $limit = $card->credit_limit === null
                    ? null : FinancialBalance::cents($card->credit_limit);
                $remaining = $limit === null ? null : max(0, $limit - $debt);

                return [
                    'name' => $card->name,
                    'active' => $card->active,
                    'debt_cents' => $debt,
                    'limit_cents' => $limit,
                    'remaining_cents' => $remaining,
                    'usage_pct' => $limit === null ? null
                        : ($limit > 0 ? min(100, (int) round($debt * 100 / $limit)) : ($debt > 0 ? 100 : 0)),
                ];
            });

        $purchasesMonth = CreditCardPurchase::query()
            ->where('user_id', $userId)
            ->whereBetween('purchased_on', [$from, $through])
            ->with('card:id,name')
            ->get(['id', 'credit_card_id', 'purchased_on', 'description', 'category', 'amount']);

        // Destino das compras no crédito é calculado pelo valor TOTAL das
        // compras na data de realização, não pela parcela da fatura. Isso
        // informa hábitos de compra sem duplicar os pagamentos das faturas.
        $cardCategories = $this->spendingCategories($purchasesMonth);
        $recentCardPurchases = $purchasesMonth
            ->sortByDesc(fn ($purchase) => $purchase->purchased_on->format('Ymd')
                .'-'.str_pad((string) $purchase->id, 12, '0', STR_PAD_LEFT))
            ->take(4)->values();

        $confirmedRecurringIds = ScheduledBill::query()
            ->where('user_id', $userId)
            ->whereNotNull('recurring_bill_id')
            ->whereDate('period_month', $from)
            ->pluck('recurring_bill_id')->all();

        $unconfirmedForecasts = RecurringBill::query()
            ->where('user_id', $userId)->where('active', true)
            ->whereDate('starts_on', '<=', $through)
            ->whereNotIn('id', $confirmedRecurringIds)
            ->get(['id', 'description', 'planned_amount'])
            ->sortBy('description')->values();

        $pending = $agenda['pending'];
        $overdue = $pending->filter(fn ($row) => $row['due_on']->lessThan($today));
        $upcomingSeven = $pending->filter(fn ($row) =>
            $row['due_on']->betweenIncluded($today, $today->addDays(7))
        );
        $dueWithinMonth = $pending->filter(fn ($row) =>
            $row['due_on']->betweenIncluded($month, $month->endOfMonth())
        );

        $incomeCents = $agenda['income_cents'];
        $paidCents = $agenda['paid_cents'];
        $difference = $incomeCents - $paidCents;
        $usableCents = $agenda['available_cents'];
        $pendingCents = $agenda['pending_cents'];

        return [
            'month_label' => ucfirst($month->locale('pt_BR')->translatedFormat('F \d\e Y')),
            'is_current_month' => $month->format('Y-m') === $today->format('Y-m'),
            'balance_cents' => $usableCents,
            'income_cents' => $incomeCents,
            'paid_cents' => $paidCents,
            'cash_difference_cents' => abs($difference),
            'cash_difference_positive' => $difference >= 0,
            'pending_cents' => $pendingCents,
            'pending_count' => $pending->count(),
            'overdue_cents' => $overdue->sum('amount_cents'),
            'overdue_count' => $overdue->count(),
            'upcoming_week_cents' => $upcomingSeven->sum('amount_cents'),
            'upcoming_week_count' => $upcomingSeven->count(),
            'month_due_cents' => $dueWithinMonth->sum('amount_cents'),
            'postponed_count' => $agenda['postponed_this_month']->count(),
            'coverage_cents' => max(0, $usableCents - $pendingCents),
            'missing_cents' => max(0, $pendingCents - $usableCents),
            'accounts' => $agenda['accounts'],
            'accounts_count' => $agenda['accounts']->where('active', true)->count(),
            'cards' => $cards,
            'card_count' => $cards->where('active', true)->count(),
            'card_debt_cents' => $cards->sum('debt_cents'),
            'card_purchases_cents' => $purchasesMonth->sum(
                fn ($purchase) => FinancialBalance::cents($purchase->amount)
            ),
            'card_purchases_count' => $purchasesMonth->count(),
            'card_categories' => $cardCategories,
            'recent_card_purchases' => $recentCardPurchases,
            'forecast_cents' => $unconfirmedForecasts->sum(
                fn ($row) => FinancialBalance::cents($row->planned_amount)
            ),
            'forecast_count' => $unconfirmedForecasts->count(),
            'forecast' => $unconfirmedForecasts->take(4)->values(),
            'categories' => $categories,
            'expense_categories_cents' => $monthExpenses->sum(
                fn ($expense) => FinancialBalance::cents($expense->amount)
            ),
            'trend' => $trend,
            'next_payments' => $pending->take(6)->values(),
            'recent_movements' => $agenda['movements']->take(7)->values(),
            'next_card_due' => $agenda['next_card_due'],
        ];
    }

    /**
     * Aplica a mesma classificação às despesas já pagas e às compras no
     * cartão. Os conjuntos nunca são agregados entre si: o pagamento de
     * uma fatura é fluxo de caixa, a compra é consumo por categoria.
     */
    private function spendingCategories(Collection $items): Collection
    {
        $totalCents = max(1, (int) $items->sum(
            fn ($item) => FinancialBalance::cents($item->amount)
        ));

        return $items->groupBy(fn ($item) => $item->category ?: 'outros')
            ->map(fn ($group, $category) => [
                'key' => $category,
                'name' => DailyExpenses::CATEGORIES[$category] ?? 'Outros',
                'cents' => (int) $group->sum(
                    fn ($item) => FinancialBalance::cents($item->amount)
                ),
                'count' => $group->count(),
            ])
            ->sortByDesc('cents')->values()
            ->map(fn ($entry, $index) => [
                ...$entry,
                'color' => self::CHART_COLORS[$index % count(self::CHART_COLORS)],
                'percent' => (int) round($entry['cents'] * 100 / $totalCents),
            ]);
    }

    private function groupByMonth(Collection $items, string $dateField): array
    {
        return $items->groupBy(fn ($item) => $item->{$dateField}->format('Y-m'))
            ->map(fn ($monthItems) => (int) $monthItems->sum(
                fn ($item) => FinancialBalance::cents($item->amount)
            ))->all();
    }
}
