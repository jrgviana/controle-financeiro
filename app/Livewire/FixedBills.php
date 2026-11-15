<?php

namespace App\Livewire;

use App\Models\FinancialAccount;
use App\Models\CreditCard;
use App\Models\CreditCardInvoice;
use App\Models\CreditCardPurchase;
use App\Services\PayCreditCardInvoice;
use App\Support\CreditCardLedger;
use App\Models\RecurringBill;
use App\Models\ScheduledBill;
use App\Services\PayScheduledBill;
use App\Support\FinancialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class FixedBills extends Component
{
    public string $selectedMonth = '';
    public bool $fixedFormOpen = false;
    public ?int $editingFixedId = null;
    public string $description = '';
    public string $category = 'moradia';
    public string $dueDay = '10';
    public string $plannedAmount = 'R$ 0,00';
    public string $startsMonth = '';
    public string $notes = '';

    public bool $occurrenceFormOpen = false;
    public ?int $occurrenceRecurringId = null;
    public string $occurrenceAmount = 'R$ 0,00';
    public string $occurrenceDueOn = '';

    public bool $oneOffFormOpen = false;
    public ?int $editingOneOffId = null;
    public string $oneOffDescription = '';
    public string $oneOffCategory = 'moradia';
    public string $oneOffAmount = 'R$ 0,00';
    public string $oneOffDueOn = '';
    public string $oneOffNotes = '';

    public bool $paymentFormOpen = false;
    public ?int $billToPayId = null;
    public string $billPaymentAccountId = '';
    public string $billPaidOn = '';
    public bool $cardPaymentOpen = false;
    public bool $cardBreakdownOpen = false;
    public ?int $cardBreakdownId = null;
    public ?int $cardInvoiceToPayId = null;
    public string $cardPayAccountId = '';
    public string $cardPayAmount = 'R$ 0,00';
    public string $cardPayDate = '';

    public function mount(): void
    {
        $this->selectedMonth = now()->format('Y-m');
        $this->startsMonth = $this->selectedMonth;
        $this->oneOffDueOn = now()->toDateString();
    }

    private function month(): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $this->selectedMonth)) {
            return CarbonImmutable::now()->startOfMonth();
        }

        return CarbonImmutable::parse($this->selectedMonth.'-01')->startOfMonth();
    }

    private function ownedFixed(int $id): RecurringBill
    {
        return RecurringBill::query()->where('user_id', Auth::id())->findOrFail($id);
    }

    private function ownedBill(int $id): ScheduledBill
    {
        return ScheduledBill::query()->where('user_id', Auth::id())->findOrFail($id);
    }

    private function moneyCents(string $value, string $field): int
    {
        if (! preg_match('/^R\$\s*(?:\d{1,3}(?:\.\d{3})+|\d+),\d{2}$/D', $value)) {
            throw ValidationException::withMessages([$field => 'Informe um valor como R$ 125,50.']);
        }

        $cents = (int) preg_replace('/\D/u', '', $value);
        if ($cents <= 0 || $cents > 99999999999) {
            throw ValidationException::withMessages([$field => 'O valor precisa ser maior que zero e até R$ 999.999.999,99.']);
        }

        return $cents;
    }

    private function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function asMoney(string $decimal): string
    {
        return 'R$ '.FinancialBalance::formatted(FinancialBalance::cents($decimal));
    }

    private function dueDate(RecurringBill $fixed, CarbonImmutable $month): CarbonImmutable
    {
        return $month->addDays(min($fixed->due_day, $month->daysInMonth) - 1);
    }

    public function openFixedForm(?int $id = null): void
    {
        $fixed = $id !== null ? $this->ownedFixed($id) : null;
        $this->resetValidation();
        $this->editingFixedId = $fixed?->id;
        $this->description = $fixed?->description ?? '';
        $this->category = $fixed?->category ?? 'moradia';
        $this->dueDay = (string) ($fixed?->due_day ?? 10);
        $this->plannedAmount = $fixed ? $this->asMoney($fixed->planned_amount) : 'R$ 0,00';
        $this->startsMonth = $fixed?->starts_on?->format('Y-m') ?? $this->month()->format('Y-m');
        $this->notes = $fixed?->notes ?? '';
        $this->fixedFormOpen = true;
    }

    public function closeFixedForm(): void
    {
        $this->fixedFormOpen = false;
        $this->editingFixedId = null;
        $this->resetValidation();
    }

    public function saveFixed(): void
    {
        $data = $this->validate([
            'description' => ['required', 'string', 'min:2', 'max:160'],
            'category' => ['required', Rule::in(array_keys(DailyExpenses::CATEGORIES))],
            'dueDay' => ['required', 'integer', 'between:1,31'],
            'plannedAmount' => ['required', 'string'],
            'startsMonth' => ['required', 'date_format:Y-m'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $cents = $this->moneyCents($data['plannedAmount'], 'plannedAmount');
        $values = [
            'description' => trim($data['description']),
            'category' => $data['category'],
            'due_day' => (int) $data['dueDay'],
            'planned_amount' => $this->amount($cents),
            'starts_on' => $data['startsMonth'].'-01',
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];

        if ($this->editingFixedId !== null) {
            $this->ownedFixed($this->editingFixedId)->update($values);
            session()->flash('success', 'Conta fixa atualizada. Meses já lançados mantêm seus valores e vencimentos.');
        } else {
            RecurringBill::create(['user_id' => Auth::id(), ...$values, 'active' => true]);
            session()->flash('success', 'Conta fixa cadastrada. Os próximos vencimentos serão exibidos mês a mês, sem descontar o saldo.');
        }

        $this->closeFixedForm();
    }

    public function toggleFixed(int $id): void
    {
        $fixed = $this->ownedFixed($id);
        $fixed->update(['active' => ! $fixed->active]);
        session()->flash('success', $fixed->active
            ? 'Conta fixa reativada para os próximos meses.'
            : 'Conta fixa pausada. Os pagamentos já registrados foram preservados.');
    }

    public function openOccurrence(int $id): void
    {
        $fixed = $this->ownedFixed($id);
        $month = $this->month();
        $period = $month->toDateString();

        $bill = ScheduledBill::query()
            ->where('user_id', Auth::id())
            ->where('recurring_bill_id', $fixed->id)
            ->whereDate('period_month', $period)
            ->first();

        if ($bill?->daily_expense_id !== null) {
            $this->addError('occurrenceAmount', 'Este mês já foi quitado.');

            return;
        }
        if (! $fixed->active && $bill === null) {
            $this->addError('occurrenceAmount', 'Reative a conta fixa antes de lançar uma nova competência.');

            return;
        }
        if ($fixed->starts_on->startOfMonth()->greaterThan($month)) {
            $this->addError('occurrenceAmount', 'Esta conta fixa ainda não começou.');

            return;
        }

        $this->resetValidation();
        $this->occurrenceRecurringId = $fixed->id;
        $this->occurrenceAmount = $bill
            ? $this->asMoney($bill->amount)
            : $this->asMoney($fixed->planned_amount);
        $this->occurrenceDueOn = $bill?->due_on?->toDateString()
            ?? $this->dueDate($fixed, $month)->toDateString();
        $this->occurrenceFormOpen = true;
    }

    public function closeOccurrence(): void
    {
        $this->occurrenceFormOpen = false;
        $this->occurrenceRecurringId = null;
        $this->resetValidation();
    }

    public function saveOccurrence(): void
    {
        if (! $this->occurrenceFormOpen || $this->occurrenceRecurringId === null) {
            return;
        }

        $data = $this->validate([
            'occurrenceAmount' => ['required', 'string'],
            'occurrenceDueOn' => ['required', 'date_format:Y-m-d'],
        ]);

        $cents = $this->moneyCents($data['occurrenceAmount'], 'occurrenceAmount');
        $month = $this->month();
        if (substr($data['occurrenceDueOn'], 0, 7) !== $month->format('Y-m')) {
            throw ValidationException::withMessages([
                'occurrenceDueOn' => 'O vencimento precisa estar dentro do mês selecionado.',
            ]);
        }

        DB::transaction(function () use ($data, $cents, $month): void {
            $fixed = RecurringBill::query()
                ->where('user_id', Auth::id())->lockForUpdate()->findOrFail($this->occurrenceRecurringId);

            $existing = ScheduledBill::query()
                ->where('user_id', Auth::id())
                ->where('recurring_bill_id', $fixed->id)
                ->whereDate('period_month', $month->toDateString())
                ->lockForUpdate()->first();

            if ($existing?->daily_expense_id !== null) {
                throw ValidationException::withMessages(['occurrenceAmount' => 'Este mês já foi pago.']);
            }
            if ($existing !== null && $cents < $existing->receivedCents()) {
                throw ValidationException::withMessages([
                    'occurrenceAmount' => 'O valor não pode ser menor que o já pago nesta conta.',
                ]);
            }
            if ($existing === null && (! $fixed->active || $fixed->starts_on->startOfMonth()->greaterThan($month))) {
                throw ValidationException::withMessages(['occurrenceAmount' => 'Conta fixa inativa ou ainda não iniciada.']);
            }

            $values = [
                'description' => $fixed->description,
                'category' => $fixed->category,
                'due_on' => $data['occurrenceDueOn'],
                'amount' => $this->amount($cents),
                'notes' => $fixed->notes,
            ];

            if ($existing) {
                $existing->update($values);
            } else {
                ScheduledBill::create([
                    'user_id' => Auth::id(),
                    'recurring_bill_id' => $fixed->id,
                    'period_month' => $month->toDateString(),
                    ...$values,
                ]);
            }
        }, 3);

        $this->closeOccurrence();
        session()->flash('success', 'Cobrança do mês confirmada. Ainda não houve pagamento nem desconto do saldo.');
    }

    public function openOneOff(?int $id = null): void
    {
        $bill = $id !== null ? $this->ownedBill($id) : null;
        if ($bill && ($bill->daily_expense_id !== null || $bill->recurring_bill_id !== null)) {
            $this->addError('oneOffDescription', 'Esta conta não pode ser editada como avulsa.');

            return;
        }
        $this->resetValidation();
        $this->editingOneOffId = $bill?->id;
        $this->oneOffDescription = $bill?->description ?? '';
        $this->oneOffCategory = $bill?->category ?? 'moradia';
        $this->oneOffAmount = $bill ? $this->asMoney($bill->amount) : 'R$ 0,00';
        $this->oneOffDueOn = $bill?->due_on?->toDateString() ?? $this->month()->toDateString();
        $this->oneOffNotes = $bill?->notes ?? '';
        $this->oneOffFormOpen = true;
    }

    public function closeOneOff(): void
    {
        $this->oneOffFormOpen = false;
        $this->editingOneOffId = null;
        $this->resetValidation();
    }

    public function saveOneOff(): void
    {
        $data = $this->validate([
            'oneOffDescription' => ['required', 'string', 'min:2', 'max:160'],
            'oneOffCategory' => ['required', Rule::in(array_keys(DailyExpenses::CATEGORIES))],
            'oneOffDueOn' => ['required', 'date_format:Y-m-d'],
            'oneOffAmount' => ['required', 'string'],
            'oneOffNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $cents = $this->moneyCents($data['oneOffAmount'], 'oneOffAmount');
        $values = [
            'description' => trim($data['oneOffDescription']),
            'category' => $data['oneOffCategory'],
            'due_on' => $data['oneOffDueOn'],
            'amount' => $this->amount($cents),
            'notes' => trim($data['oneOffNotes'] ?? '') ?: null,
        ];

        if ($this->editingOneOffId !== null) {
            $bill = $this->ownedBill($this->editingOneOffId);
            if ($bill->daily_expense_id !== null || $bill->recurring_bill_id !== null) {
                throw ValidationException::withMessages(['oneOffDescription' => 'Conta já paga ou vinculada a outra competência.']);
            }
            if ($cents < $bill->receivedCents()) {
                throw ValidationException::withMessages([
                    'oneOffAmount' => 'O valor não pode ser menor que o já pago nesta conta.',
                ]);
            }
            $bill->update($values);
        } else {
            ScheduledBill::create(['user_id' => Auth::id(), ...$values]);
        }

        $this->selectedMonth = substr($data['oneOffDueOn'], 0, 7);
        $this->closeOneOff();
        session()->flash('success', 'Conta avulsa registrada sem afetar o saldo.');
    }

    public function removeOneOff(int $id): void
    {
        $bill = $this->ownedBill($id);
        if ($bill->recurring_bill_id !== null || $bill->daily_expense_id !== null || $bill->receivedCents() > 0) {
            throw ValidationException::withMessages(['oneOffDescription' => 'Conta com pagamento registrado não pode ser cancelada.']);
        }
        $bill->delete();
        session()->flash('success', 'Cobrança avulsa cancelada sem alterar os saldos.');
    }

    public function openPayment(int $id): void
    {
        $bill = $this->ownedBill($id);
        if ($bill->daily_expense_id !== null) {
            $this->addError('billPaymentAccountId', 'Esta cobrança já foi quitada.');

            return;
        }

        $this->resetValidation();
        $this->billToPayId = $bill->id;
        $accountId = FinancialAccount::query()
            ->where('user_id', Auth::id())->where('active', true)
            ->orderBy('name')->value('id');
        $this->billPaymentAccountId = $accountId !== null ? (string) $accountId : '';
        $this->billPaidOn = now()->toDateString();
        $this->paymentFormOpen = true;
    }

    public function closePayment(): void
    {
        $this->paymentFormOpen = false;
        $this->billToPayId = null;
        $this->resetValidation();
    }

    public function confirmPayment(): void
    {
        if (! $this->paymentFormOpen || $this->billToPayId === null) {
            return;
        }

        $data = $this->validate([
            'billPaymentAccountId' => [
                'required', 'integer',
                Rule::exists('financial_accounts', 'id')->where('user_id', Auth::id())->where('active', true),
            ],
            'billPaidOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        app(PayScheduledBill::class)->execute(
            (int) Auth::id(), $this->billToPayId,
            (int) $data['billPaymentAccountId'], $data['billPaidOn']
        );

        $this->closePayment();
        session()->flash('success', 'Pagamento registrado uma única vez em Gastos e descontado do saldo da conta escolhida.');
    }

    public function jumpToCardMonth(string $month): void
    {
        if (preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $month)) {
            $this->selectedMonth = $month;
        }
    }

    public function openCardBreakdown(int $cardId): void
    {
        CreditCard::query()->where('user_id', Auth::id())->findOrFail($cardId);
        $this->cardBreakdownId = $cardId;
        $this->cardBreakdownOpen = true;
        $this->resetValidation();
    }

    public function closeCardBreakdown(): void
    {
        $this->cardBreakdownOpen = false;
        $this->cardBreakdownId = null;
    }

    public function openCardPayment(int $invoiceId): void
    {
        $invoice = CreditCardInvoice::query()
            ->where('user_id', Auth::id())
            ->whereHas('card', fn ($query) => $query->where('user_id', Auth::id()))
            ->findOrFail($invoiceId);
        $card = CreditCard::query()->where('user_id', Auth::id())->findOrFail($invoice->credit_card_id);
        $row = app(CreditCardLedger::class)->invoices($card)
            ->first(fn ($entry) => $entry['invoice']->id === $invoice->id);

        if ($row === null || $row['remaining_cents'] <= 0) {
            throw ValidationException::withMessages([
                'cardPayAmount' => 'Esta fatura já está quitada.',
            ]);
        }

        $this->resetValidation();
        $this->closeCardBreakdown();
        $this->cardInvoiceToPayId = $invoice->id;
        $account = FinancialAccount::query()->where('user_id', Auth::id())
            ->where('active', true)->orderBy('name')->first();
        $this->cardPayAccountId = $account ? (string) $account->id : '';
        $this->cardPayAmount = 'R$ '.FinancialBalance::formatted($row['remaining_cents']);
        $this->cardPayDate = now()->toDateString();
        $this->cardPaymentOpen = true;
    }

    public function closeCardPayment(): void
    {
        $this->cardPaymentOpen = false;
        $this->cardInvoiceToPayId = null;
        $this->resetValidation();
    }

    public function confirmCardPayment(): void
    {
        if (! $this->cardPaymentOpen || $this->cardInvoiceToPayId === null) {
            return;
        }

        $data = $this->validate([
            'cardPayAccountId' => [
                'required', 'integer',
                Rule::exists('financial_accounts', 'id')->where('user_id', Auth::id())->where('active', true),
            ],
            'cardPayAmount' => ['required', 'string'],
            'cardPayDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $amountCents = $this->moneyCents($data['cardPayAmount'], 'cardPayAmount');
        $result = app(PayCreditCardInvoice::class)->execute(
            (int) Auth::id(),
            $this->cardInvoiceToPayId,
            (int) $data['cardPayAccountId'],
            $amountCents,
            $data['cardPayDate'],
        );

        $this->closeCardPayment();
        session()->flash('success',
            $result['remaining_cents'] > 0
                ? 'Pagamento parcial registrado. O saldo restante permanece em aberto; juros podem ser lançados em Cartões.'
                : 'Fatura quitada. Valor descontado uma única vez da conta escolhida.'
        );
    }

    public function render()
    {
        $month = $this->month();
        $monthStart = $month->toDateString();
        $monthEnd = $month->endOfMonth()->toDateString();

        $fixedPlans = RecurringBill::query()
            ->where('user_id', Auth::id())
            ->whereDate('starts_on', '<=', $monthEnd)
            ->orderBy('due_day')->orderBy('description')->get();

        $occurrences = ScheduledBill::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('recurring_bill_id')
            ->whereDate('period_month', $monthStart)
            ->with('payment.financialAccount')
            ->get()->keyBy('recurring_bill_id');

        $fixedRows = $fixedPlans->filter(
            fn ($fixed) => $fixed->active || $occurrences->has($fixed->id)
        )->map(function ($fixed) use ($occurrences, $month): array {
            $bill = $occurrences->get($fixed->id);
            $amount = FinancialBalance::cents($bill?->amount ?? $fixed->planned_amount);

            return [
                'fixed' => $fixed,
                'bill' => $bill,
                'amount_cents' => $amount,
                'due_on' => $bill?->due_on ?? $this->dueDate($fixed, $month),
                'paid' => $bill?->daily_expense_id !== null,
            ];
        })->values();

        // Registros anteriores a este módulo permanecem como contas avulsas.
        $oneOff = ScheduledBill::query()
            ->where('user_id', Auth::id())
            ->whereNull('recurring_bill_id')
            ->whereBetween('due_on', [$monthStart, $monthEnd])
            ->with('payment.financialAccount')
            ->orderBy('due_on')->get();

        $plannedCents = $fixedRows->sum(fn ($r) => $r['amount_cents'])
            + $oneOff->sum(fn ($b) => FinancialBalance::cents($b->amount));

        $paidCents = $fixedRows->filter(fn ($r) => $r['paid'])->sum(fn ($r) => $r['amount_cents'])
            + $oneOff->filter(fn ($b) => $b->daily_expense_id !== null)
                ->sum(fn ($b) => FinancialBalance::cents($b->amount));

        // As compras sempre entram no mês do vencimento REAL registrado no
        // fechamento do cartão, e nunca no mês da compra por aproximação.
        $cardInvoices = collect();
        $nextCardMonth = null;
        $nextCardMonthCents = 0;
        $ledger = app(CreditCardLedger::class);

        foreach (CreditCard::query()->where('user_id', Auth::id())->get() as $card) {
            foreach ($ledger->invoices($card) as $row) {
                $invoice = $row['invoice'];
                $invoiceMonth = $invoice->due_on->format('Y-m');

                if ($row['installments_cents'] === 0 && $row['interest_cents'] === 0
                    && $row['paid_cents'] === 0 && $row['carry_cents'] === 0) {
                    continue;
                }

                if ($invoiceMonth === $month->format('Y-m')) {
                    $plannedAdvances = $invoice->installments
                        ->filter(fn ($item) => $item->scheduledPlan !== null)
                        ->sum(fn ($item) => FinancialBalance::cents($item->amount));

                    $cardInvoices->push([
                        'invoice' => $invoice,
                        'card' => $card,
                        'row' => $row,
                        'planned_advance_cents' => $plannedAdvances,
                    ]);
                    continue;
                }

                if ($invoiceMonth > $month->format('Y-m') && $row['installments_cents'] > 0) {
                    if ($nextCardMonth === null || $invoiceMonth < $nextCardMonth) {
                        $nextCardMonth = $invoiceMonth;
                        $nextCardMonthCents = $row['installments_cents'];
                    } elseif ($nextCardMonth === $invoiceMonth) {
                        $nextCardMonthCents += $row['installments_cents'];
                    }
                }
            }
        }

        $cardInvoices = $cardInvoices
            ->sortBy(fn ($item) => $item['invoice']->due_on->format('Y-m-d').'|'.$item['card']->name)
            ->values();

        $cardMonthCents = $cardInvoices->sum(fn ($item) => $item['row']['installments_cents']);
        $cardAdvancedCents = $cardInvoices->sum(fn ($item) => $item['planned_advance_cents']);
        $cardInvoiceRemainingCents = $cardInvoices->sum(fn ($item) => $item['row']['remaining_cents']);
        $cardInvoicePaidCents = $cardInvoices->sum(fn ($item) => $item['row']['paid_cents']);
        // O resumo mensal considera o total de cada fatura (incluindo encargos
        // e eventuais saldos transportados), não apenas parcelas novas.
        // Pagamentos são detalhados separadamente para não confundir
        // compromissos previstos com valores ainda a pagar.
        $cardInvoiceTotalCents = $cardInvoices->sum(fn ($item) => $item['row']['total_cents']);
        $totalScheduledCents = $plannedCents + $cardInvoiceTotalCents;
        $totalPaidCents = $paidCents + $cardInvoicePaidCents;
        $totalPendingCents = max(0, $plannedCents - $paidCents) + $cardInvoiceRemainingCents;

        // Duas datas distintas: a data da COMPRA e a do VENCIMENTO.
        // Uma compra de novembro pode ser paga somente em dezembro ou janeiro.
        // Este histórico dá visibilidade imediata ao lançamento sem contar a
        // parcela na competência errada nem criar qualquer débito bancário.
        $cardPurchasesMadeInMonth = CreditCardPurchase::query()
            ->where('user_id', Auth::id())
            ->whereBetween('purchased_on', [$monthStart, $monthEnd])
            ->with(['card:id,name', 'installments.invoice'])
            ->orderByDesc('purchased_on')
            ->orderByDesc('id')
            ->get()
            ->map(function (CreditCardPurchase $purchase): array {
                $firstInstallment = $purchase->installments
                    ->sortBy('installment_number')
                    ->first();

                return [
                    'purchase' => $purchase,
                    'first_installment' => $firstInstallment,
                    'first_due_on' => $firstInstallment?->invoice?->due_on,
                    'first_installment_cents' => $firstInstallment
                        ? FinancialBalance::cents($firstInstallment->amount)
                        : 0,
                ];
            });

        $cardPurchasesMadeCount = $cardPurchasesMadeInMonth->count();
        $newPurchaseFirstInstallmentsCents = $cardPurchasesMadeInMonth
            ->sum(fn ($entry) => $entry['first_installment_cents']);

        // O quadro dos cartões mostra os compromissos de seu mês de referência:
        // faturas que vencem agora + primeiras parcelas das compras novas cujo
        // vencimento ocorre em mês posterior. Não se somam duas vezes parcelas
        // que já estão na fatura do mês selecionado.
        $futureFirstInstallmentsCents = $cardPurchasesMadeInMonth
            ->filter(fn ($entry) => $entry['first_due_on']?->greaterThan($month->endOfMonth()) ?? false)
            ->sum(fn ($entry) => $entry['first_installment_cents']);
        $cardOverviewCents = $cardInvoiceTotalCents + $futureFirstInstallmentsCents;
        $totalOverviewCents = $plannedCents + $cardOverviewCents;

        // Uma única lista de pagamentos previstos por cartão. O mês mostra
        // faturas que vencem agora e a primeira parcela de compras lançadas
        // agora cujo vencimento será em outro mês. Os valores não são duplicados:
        // uma parcela que já consta na fatura do mês NÃO entra de novo.
        $cardPaymentSummaries = collect();
        $cardsForSummary = CreditCard::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        foreach ($cardsForSummary as $card) {
            $groups = collect();

            foreach ($cardInvoices->filter(fn ($entry) => $entry['card']->id === $card->id) as $entry) {
                $invoice = $entry['invoice'];
                $row = $entry['row'];

                $items = $invoice->installments->map(fn ($installment) => [
                    'description' => $installment->purchase?->description ?? 'Compra',
                    'installment_label' => $installment->installment_number
                        .'/'.($installment->purchase?->installments_count ?? '?'),
                    'amount_cents' => FinancialBalance::cents($installment->amount),
                    'advanced' => $installment->scheduledPlan !== null || $installment->advance !== null,
                ])->values();

                if ($row['carry_cents'] > 0) {
                    $items->push([
                        'description' => 'Saldo anterior',
                        'installment_label' => '',
                        'amount_cents' => $row['carry_cents'],
                        'advanced' => false,
                    ]);
                }
                foreach ($invoice->charges as $charge) {
                    $items->push([
                        'description' => $charge->description,
                        'installment_label' => 'Encargos',
                        'amount_cents' => FinancialBalance::cents($charge->amount),
                        'advanced' => false,
                    ]);
                }

                $groups->push([
                    'invoice' => $invoice,
                    'due_on' => $invoice->due_on,
                    'amount_cents' => $row['total_cents'],
                    'paid_cents' => $row['paid_cents'],
                    'remaining_cents' => $row['remaining_cents'],
                    'items' => $items,
                    'is_current_due' => true,
                ]);
            }

            $futurePurchases = $cardPurchasesMadeInMonth
                ->filter(fn ($entry) => $entry['purchase']->credit_card_id === $card->id
                    && ($entry['first_due_on']?->greaterThan($month->endOfMonth()) ?? false))
                ->groupBy(fn ($entry) => (string) $entry['first_installment']->credit_card_invoice_id);

            foreach ($futurePurchases as $purchases) {
                $first = $purchases->first();
                $invoice = $first['first_installment']->invoice;
                $items = $purchases->map(fn ($entry) => [
                    'description' => $entry['purchase']->description,
                    'installment_label' => '1/'.$entry['purchase']->installments_count,
                    'amount_cents' => $entry['first_installment_cents'],
                    'advanced' => false,
                ])->values();

                $value = $purchases->sum('first_installment_cents');
                $groups->push([
                    'invoice' => $invoice,
                    'due_on' => $invoice->due_on,
                    'amount_cents' => $value,
                    'paid_cents' => 0,
                    'remaining_cents' => $value,
                    'items' => $items,
                    'is_current_due' => false,
                ]);
            }

            $amountCents = $groups->sum('amount_cents');
            if ($amountCents > 0) {
                $cardPaymentSummaries->push([
                    'card' => $card,
                    'amount_cents' => $amountCents,
                    'groups' => $groups->sortBy(fn ($group) => $group['due_on']->format('Y-m-d'))->values(),
                ]);
            }
        }

        $selectedCardBreakdown = $this->cardBreakdownOpen && $this->cardBreakdownId !== null
            ? $cardPaymentSummaries->first(fn ($entry) => $entry['card']->id === $this->cardBreakdownId)
            : null;

        $cardInvoiceToPay = null;
        if ($this->cardInvoiceToPayId !== null) {
            $cardInvoiceToPay = $cardInvoices
                ->first(fn ($item) => $item['invoice']->id === $this->cardInvoiceToPayId);
        }

        $billToPay = $this->billToPayId !== null
            ? ScheduledBill::query()->where('user_id', Auth::id())->find($this->billToPayId)
            : null;

        $activeAccounts = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->where('active', true)->orderBy('name')->get();

        return view('livewire.fixed-bills', [
            'fixedRows' => $fixedRows,
            'cardInvoices' => $cardInvoices,
            'cardPaymentSummaries' => $cardPaymentSummaries,
            'selectedCardBreakdown' => $selectedCardBreakdown,
            'cardPurchasesMadeInMonth' => $cardPurchasesMadeInMonth,
            'cardPurchasesMadeCount' => $cardPurchasesMadeCount,
            'newPurchaseFirstInstallmentsCents' => $newPurchaseFirstInstallmentsCents,
            'cardOverviewCents' => $cardOverviewCents,
            'totalOverviewCents' => $totalOverviewCents,
            'cardMonthCents' => $cardMonthCents,
            'cardAdvancedCents' => $cardAdvancedCents,
            'cardInvoiceRemainingCents' => $cardInvoiceRemainingCents,
            'cardInvoicePaidCents' => $cardInvoicePaidCents,
            'cardInvoiceTotalCents' => $cardInvoiceTotalCents,
            'totalPaidCents' => $totalPaidCents,
            'totalPendingCents' => $totalPendingCents,
            'nextCardMonth' => $nextCardMonth,
            'nextCardMonthCents' => $nextCardMonthCents,
            'cardInvoiceToPay' => $cardInvoiceToPay,
            'totalScheduledCents' => $totalScheduledCents,
            'oneOff' => $oneOff,
            'plannedCents' => $plannedCents,
            'paidCents' => $paidCents,
            'pendingCents' => max(0, $plannedCents - $paidCents),
            'activeAccounts' => $activeAccounts,
            'billToPay' => $billToPay,
            'categories' => DailyExpenses::CATEGORIES,
        ])->layout('layouts.app');
    }
}
