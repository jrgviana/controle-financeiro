<?php

namespace App\Livewire;

use App\Models\FinancialAccount;
use App\Models\CreditCard;
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
        if ($bill->recurring_bill_id !== null || $bill->daily_expense_id !== null) {
            throw ValidationException::withMessages(['oneOffDescription' => 'Conta vinculada ou já paga não pode ser cancelada.']);
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

        // Utilize sempre o vencimento REAL da parcela gravado na fatura
        // pela lógica de fechamento/melhor dia de compra do cartão.
        // Não projetar o total da compra para o mês do lançamento.
        $cardInstallments = collect();
        $ledger = app(CreditCardLedger::class);
        foreach (CreditCard::query()->where('user_id', Auth::id())->get() as $card) {
            foreach ($ledger->invoices($card) as $invoiceRow) {
                $invoice = $invoiceRow['invoice'];
                if ($invoice->due_on->format('Y-m') !== $month->format('Y-m')) {
                    continue;
                }

                foreach ($invoice->installments as $installment) {
                    $cardInstallments->push([
                        'card' => $card,
                        'invoice' => $invoice,
                        'installment' => $installment,
                        'purchase' => $installment->purchase,
                        'amount_cents' => FinancialBalance::cents($installment->amount),
                        'advanced' => $installment->advance !== null,
                        'invoice_paid' => $invoiceRow['remaining_cents'] === 0,
                        'invoice_partially_paid' => $invoiceRow['paid_cents'] > 0,
                    ]);
                }
            }
        }

        $cardInstallments = $cardInstallments
            ->sortBy(fn ($row) => $row['invoice']->due_on->format('Y-m-d')
                .'|'.$row['card']->name
                .'|'.str_pad((string) $row['installment']->id, 12, '0', STR_PAD_LEFT))
            ->values();

        $cardMonthCents = $cardInstallments->sum(fn ($row) => $row['amount_cents']);
        $cardAdvancedCents = $cardInstallments
            ->filter(fn ($row) => $row['advanced'])
            ->sum(fn ($row) => $row['amount_cents']);

        $billToPay = $this->billToPayId !== null
            ? ScheduledBill::query()->where('user_id', Auth::id())->find($this->billToPayId)
            : null;

        $activeAccounts = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->where('active', true)->orderBy('name')->get();

        return view('livewire.fixed-bills', [
            'fixedRows' => $fixedRows,
            'cardInstallments' => $cardInstallments,
            'cardMonthCents' => $cardMonthCents,
            'cardAdvancedCents' => $cardAdvancedCents,
            'totalScheduledCents' => $plannedCents + $cardMonthCents,
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
