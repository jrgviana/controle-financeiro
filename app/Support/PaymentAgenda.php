<?php

namespace App\Support;

use App\Models\AccountTransfer;
use App\Models\CreditCard;
use App\Models\CreditCardInvoicePayment;
use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\PaymentPostponement;
use App\Models\ReceivedIncome;
use App\Models\RecurringBill;
use App\Models\ScheduledBill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PaymentAgenda
{
    /**
     * Lista compromissos até o mês selecionado sem modificar o banco.
     *
     * O cadastro de recorrência é apenas uma previsão: entra em Pagamentos
     * somente depois de confirmar a cobrança do mês em Contas mensais.
     * Contas confirmadas e não pagas mantêm o vencimento original nos meses
     * seguintes. Adiamentos registrados antes desta regra também continuam
     * válidos.
     * Para cartões, pagamentos são alocados cronologicamente sobre as
     * parcelas/encargos: saldos transportados NÃO são novas dívidas.
     */
    public function forMonth(int $userId, string $yearMonth): array
    {
        $month = CarbonImmutable::parse($yearMonth.'-01')->startOfMonth();
        $from = $month->toDateString();
        $to = $month->endOfMonth()->toDateString();
        $today = CarbonImmutable::today();

        $accounts = FinancialAccount::query()
            ->where('user_id', $userId)->orderByDesc('active')->orderBy('name')->get()
            ->map(function (FinancialAccount $account): array {
                $balance = app(FinancialBalance::class)->currentCents($account);

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'active' => $account->active,
                    'balance_cents' => $balance,
                    'usable_cents' => $account->active ? max(0, $balance) : 0,
                ];
            });

        $rows = collect();
        $postponements = PaymentPostponement::query()
            ->where('user_id', $userId)
            ->whereDate('from_month', '<=', $from)
            ->orderBy('from_month')
            ->get();
        $priorDeferred = $postponements->groupBy('payable_key');

        $plans = RecurringBill::query()->where('user_id', $userId)
            ->whereDate('starts_on', '<=', $to)->orderBy('description')->get();

        $occurrences = ScheduledBill::query()->where('user_id', $userId)
            ->whereNotNull('recurring_bill_id')
            ->whereDate('period_month', '<=', $from)
            ->with(['payment', 'partialPayments.expense'])
            ->get()
            ->keyBy(fn ($bill) => $bill->recurring_bill_id.'|'.$bill->period_month->format('Y-m'));

        $handledOccurrences = [];
        foreach ($plans as $plan) {
            $cursor = CarbonImmutable::parse($plan->starts_on->format('Y-m-01'));
            while ($cursor->lessThanOrEqualTo($month)) {
                $period = $cursor->format('Y-m');
                $bill = $occurrences->get($plan->id.'|'.$period);

                if ($bill !== null) {
                    $handledOccurrences[$bill->id] = true;
                }

                $key = 'recurring:'.$plan->id.':'.$period;
                // Cadastro recorrente não é uma cobrança. Só consta na lista
                // se a competência foi confirmada ou se o usuário já havia
                // registrado um adiamento explícito antes desta alteração.
                $deferred = $priorDeferred->get($key)?->last();
                if (($bill !== null || $deferred !== null) && $bill?->daily_expense_id === null) {
                    $due = $bill?->due_on
                        ? CarbonImmutable::parse($bill->due_on->toDateString())
                        : $cursor->addDays(min($plan->due_day, $cursor->daysInMonth) - 1);
                    $remaining = $bill?->remainingCents()
                        ?? FinancialBalance::cents($deferred->remaining_amount);
                    $paid = $bill?->receivedCents() ?? 0;

                    if ($remaining > 0) {
                        $rows->push([
                            'key' => 'recurring:'.$plan->id.':'.$period,
                            'kind' => 'recurring',
                            'id' => $plan->id,
                            'period' => $period,
                            'description' => $bill?->description ?? $plan->description,
                            'detail' => 'Conta mensal · '.substr($period, 5, 2).'/'.substr($period, 0, 4),
                            'due_on' => $due,
                            'amount_cents' => $remaining,
                            'paid_cents' => $paid,
                            'status' => $due->lessThan($today) ? 'Vencida' : ($paid > 0 ? 'Parcial' : 'A vencer'),
                        ]);
                    }
                }

                $cursor = $cursor->addMonth();
            }
        }

        // Exibe ocorrências órfãs/inativas já cadastradas e pendentes,
        // preservando histórico mesmo após exclusão lógica do modelo.
        foreach ($occurrences as $bill) {
            if (isset($handledOccurrences[$bill->id]) || $bill->daily_expense_id !== null) {
                continue;
            }
            $due = CarbonImmutable::parse($bill->due_on->toDateString());
            $remaining = $bill->remainingCents();
            if ($remaining > 0) {
                $paid = $bill->receivedCents();
                $rows->push([
                    'key' => 'bill:'.$bill->id,
                    'kind' => 'bill',
                    'id' => $bill->id,
                    'period' => null,
                    'description' => $bill->description,
                    'detail' => 'Conta mensal registrada',
                    'due_on' => $due,
                    'amount_cents' => $remaining,
                    'paid_cents' => $paid,
                    'status' => $due->lessThan($today) ? 'Vencida' : ($paid > 0 ? 'Parcial' : 'A vencer'),
                ]);
            }
        }

        $singleBills = ScheduledBill::query()->where('user_id', $userId)
            ->whereNull('recurring_bill_id')->whereNull('daily_expense_id')
            ->whereDate('due_on', '<=', $to)->with('partialPayments.expense')->get();

        foreach ($singleBills as $bill) {
            $due = CarbonImmutable::parse($bill->due_on->toDateString());
            $remaining = $bill->remainingCents();
            if ($remaining > 0) {
                $paid = $bill->receivedCents();
                $rows->push([
                    'key' => 'bill:'.$bill->id,
                    'kind' => 'bill',
                    'id' => $bill->id,
                    'period' => null,
                    'description' => $bill->description,
                    'detail' => 'Conta cadastrada',
                    'due_on' => $due,
                    'amount_cents' => $remaining,
                    'paid_cents' => $paid,
                    'status' => $due->lessThan($today) ? 'Vencida' : ($paid > 0 ? 'Parcial' : 'A vencer'),
                ]);
            }
        }

        $nextCardDue = null;
        foreach (CreditCard::query()->where('user_id', $userId)->get() as $card) {
            $invoices = app(CreditCardLedger::class)->invoices($card);
            $paymentsRemaining = $invoices->sum('paid_cents');

            foreach ($invoices as $entry) {
                // Não somar carry_cents: é o mesmo dinheiro pendente
                // representado na fatura anterior.
                $ownCents = $entry['installments_cents'] + $entry['interest_cents'];
                $amountCents = max(0, $ownCents - $paymentsRemaining);
                $paymentsRemaining = max(0, $paymentsRemaining - $ownCents);
                if ($amountCents === 0) {
                    continue;
                }

                $invoice = $entry['invoice'];
                $due = CarbonImmutable::parse($invoice->due_on->toDateString());
                if ($due->greaterThan($month->endOfMonth())) {
                    if ($nextCardDue === null || $due->lessThan($nextCardDue)) {
                        $nextCardDue = $due;
                    }
                    continue;
                }

                $rows->push([
                    'key' => 'card:'.$invoice->id,
                    'kind' => 'card',
                    'id' => $invoice->id,
                    'period' => null,
                    'description' => $card->name,
                    'detail' => 'Fatura · '.$invoice->installments->count().' parcela(s)',
                    'due_on' => $due,
                    'amount_cents' => $amountCents,
                    'paid_cents' => max(0, $ownCents - $amountCents),
                    'status' => $due->lessThan($today) ? 'Vencida' : 'A vencer',
                ]);
            }
        }

        // A dívida permanece com o vencimento original; apenas sua
        // apresentação é adiada ao mês seguinte após a decisão do usuário
        // ou após um pagamento parcial. Nenhum débito é criado.
        $showFrom = $postponements->groupBy('payable_key')->map(
            fn ($events) => $events->max(fn ($event) => $event->to_month->format('Y-m'))
        );
        $rows = $rows->filter(fn ($row) =>
            ! $showFrom->has($row['key']) || $showFrom->get($row['key']) <= $yearMonth
        );
        $postponedThisMonth = $postponements->filter(fn ($event) =>
            $event->from_month->format('Y-m') === $yearMonth
        )->values();

        $rows = $rows->sortBy(fn ($row) =>
            $row['due_on']->format('Y-m-d').'|'.$row['description'].'|'.$row['key']
        )->values();

        $incomeCents = FinancialBalance::cents(ReceivedIncome::query()
            ->where('user_id', $userId)->whereBetween('received_on', [$from, $to])->sum('amount'));
        $spendingCents = FinancialBalance::cents(DailyExpense::query()
            ->where('user_id', $userId)->whereBetween('paid_on', [$from, $to])->sum('amount'));
        $cardPaidCents = FinancialBalance::cents(CreditCardInvoicePayment::query()
            ->where('user_id', $userId)->whereBetween('paid_on', [$from, $to])->sum('amount'));

        $movements = $this->movements($userId, $from, $to);

        return [
            'accounts' => $accounts,
            'available_cents' => $accounts->sum('usable_cents'),
            'income_cents' => $incomeCents,
            'paid_cents' => $spendingCents + $cardPaidCents,
            'pending' => $rows,
            'postponed_this_month' => $postponedThisMonth,
            'pending_cents' => $rows->sum('amount_cents'),
            'overdue_cents' => $rows->where('status', 'Vencida')->sum('amount_cents'),
            'next_card_due' => $nextCardDue,
            'movements' => $movements,
        ];
    }

    private function movements(int $userId, string $from, string $to): Collection
    {
        $incomes = ReceivedIncome::query()->where('user_id', $userId)
            ->whereBetween('received_on', [$from, $to])->with('financialAccount')->get()
            ->map(fn ($item) => [
                'date' => $item->received_on->toDateString(),
                'description' => $item->description,
                'account' => $item->financialAccount?->name ?? 'Conta',
                'amount_cents' => FinancialBalance::cents($item->amount),
                'type' => 'Entrada',
            ]);

        $expenses = DailyExpense::query()->where('user_id', $userId)
            ->whereBetween('paid_on', [$from, $to])->with('financialAccount')->get()
            ->map(fn ($item) => [
                'date' => $item->paid_on->toDateString(),
                'description' => $item->description,
                'account' => $item->financialAccount?->name ?? 'Conta',
                'amount_cents' => FinancialBalance::cents($item->amount),
                'type' => 'Pagamento',
            ]);

        $cards = CreditCardInvoicePayment::query()->where('user_id', $userId)
            ->whereBetween('paid_on', [$from, $to])->with(['invoice.card', 'financialAccount'])->get()
            ->map(fn ($item) => [
                'date' => $item->paid_on->toDateString(),
                'description' => 'Fatura · '.($item->invoice?->card?->name ?? 'Cartão'),
                'account' => $item->financialAccount?->name ?? 'Conta',
                'amount_cents' => FinancialBalance::cents($item->amount),
                'type' => 'Pagamento',
            ]);

        $transfers = AccountTransfer::query()->where('user_id', $userId)
            ->whereBetween('transferred_on', [$from, $to])
            ->with(['fromAccount', 'toAccount'])->get()
            ->map(fn ($item) => [
                'date' => $item->transferred_on->toDateString(),
                'description' => ($item->fromAccount?->name ?? 'Origem').' → '.($item->toAccount?->name ?? 'Destino'),
                'account' => 'Entre contas',
                'amount_cents' => FinancialBalance::cents($item->amount),
                'type' => 'Transferência',
            ]);

        return $incomes->concat($expenses)->concat($cards)->concat($transfers)
            ->sortByDesc('date')->values();
    }
}
