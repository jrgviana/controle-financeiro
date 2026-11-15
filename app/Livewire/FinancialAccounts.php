<?php

namespace App\Livewire;

use App\Models\FinancialAccount;
use App\Models\ScheduledBill;
use App\Models\CreditCard;
use App\Models\CreditCardInvoice;
use App\Models\CreditCardInvoiceCharge;
use App\Services\PayScheduledBill;
use App\Services\PayCreditCardInvoice;
use App\Support\CreditCardLedger;
use App\Services\TransferFunds;
use App\Support\FinancialBalance;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class FinancialAccounts extends Component
{
    public bool $formOpen = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'corrente';

    public string $institution = '';

    public string $initialBalance = 'R$ 0,00';

    public bool $transferModalOpen = false;
    public ?int $transferSourceId = null;
    public string $transferTargetId = '';
    public string $transferMode = 'transfer';
    public string $transferAmount = 'R$ 0,00';
    public string $transferDate = '';
    public string $transferNotes = '';
    public int $transferFormVersion = 0;

    public bool $billFormOpen = false;
    public ?int $editingBillId = null;
    public string $billDescription = '';
    public string $billCategory = 'moradia';
    public string $billDueOn = '';
    public string $billAmount = 'R$ 0,00';
    public string $billNotes = '';

    public bool $billPaymentModalOpen = false;
    public ?int $billToPayId = null;
    public string $billPaymentAccountId = '';
    public string $billPaidOn = '';

    public bool $cardPaymentModalOpen = false;
    public ?int $cardInvoiceToPayId = null;
    public string $cardPaymentAccountId = '';
    public string $cardPaymentAmount = 'R$ 0,00';
    public string $cardPaymentOn = '';
    public string $cardInterestDescription = 'Juros/encargos informados pelo banco';
    public string $cardInterestAmount = 'R$ 0,00';


    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->type = 'corrente';
        $this->institution = '';
        $this->initialBalance = 'R$ 0,00';
        $this->formOpen = true;
    }

    public function edit(int $id): void
    {
        $account = $this->ownedAccount($id);

        $this->resetValidation();
        $this->editingId = $account->id;
        $this->name = $account->name;
        $this->type = $account->type;
        $this->institution = $account->institution ?? '';
        $this->initialBalance = 'R$ '.number_format((float) $account->initial_balance, 2, ',', '.');
        $this->formOpen = true;
    }

    public function cancel(): void
    {
        $this->formOpen = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['required', Rule::in(['corrente', 'poupanca', 'carteira', 'outras'])],
            'institution' => ['nullable', 'string', 'max:100'],
            'initialBalance' => ['required', 'regex:/^(?:R\$\s*)?(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?$/D'],
        ], [
            'name.required' => 'Informe o nome da conta.',
            'name.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'type.in' => 'Escolha um tipo de conta válido.',
            'initialBalance.required' => 'Informe o saldo inicial, mesmo que seja zero.',
            'initialBalance.regex' => 'Informe um valor válido, como 0,00 ou 1.250,50.',
        ]);

        $amount = preg_replace('/^R\$\s*/u', '', trim($data['initialBalance']));
        $normalized = str_replace(',', '.', str_replace('.', '', $amount));

        if ((float) $normalized > 999999999.99) {
            $this->addError('initialBalance', 'O saldo informado ultrapassa o limite permitido.');

            return;
        }

        $values = [
            'name' => trim($data['name']),
            'type' => $data['type'],
            'institution' => trim($data['institution'] ?? '') ?: null,
            'initial_balance' => number_format((float) $normalized, 2, '.', ''),
        ];

        if ($this->editingId !== null) {
            $this->ownedAccount($this->editingId)->update($values);
            session()->flash('success', 'Conta atualizada com sucesso.');
        } else {
            FinancialAccount::create([
                'user_id' => Auth::id(),
                ...$values,
            ]);
            session()->flash('success', 'Conta cadastrada com sucesso.');
        }

        $this->formOpen = false;
        $this->editingId = null;
    }

    public function toggleActive(int $id): void
    {
        $account = $this->ownedAccount($id);
        $account->update(['active' => ! $account->active]);

        session()->flash('success', $account->active
            ? 'Conta reativada com sucesso.'
            : 'Conta desativada. Seus dados foram preservados.');
    }


    /**
     * Abre o formulário diretamente na linha da conta com saldo disponível.
     */
    public function openTransfer(int $accountId): void
    {
        $account = $this->ownedAccount($accountId);

        if (! $account->active) {
            $this->addError('transferSourceId', 'Esta conta está desativada.');

            return;
        }

        $available = (new FinancialBalance())->currentCents($account);

        if ($available <= 0) {
            $this->addError('transferSourceId', 'Esta conta não possui saldo disponível para movimentar.');

            return;
        }

        $this->resetValidation();
        $this->transferSourceId = $account->id;
        $this->transferMode = 'transfer';
        $this->transferDate = now()->toDateString();
        $this->transferAmount = 'R$ 0,00';
        $this->transferNotes = '';

        $this->chooseTransferDestination();
        $this->transferFormVersion++;
        $this->transferModalOpen = true;
    }

    public function updatedTransferMode(): void
    {
        if (! in_array($this->transferMode, ['transfer', 'withdrawal'], true)) {
            $this->transferMode = 'transfer';
        }

        $this->resetValidation();
        $this->chooseTransferDestination();
        $this->transferFormVersion++;
    }

    private function chooseTransferDestination(): void
    {
        $this->transferTargetId = '';

        if ($this->transferSourceId === null) {
            return;
        }

        $query = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->where('active', true)
            ->where('id', '!=', $this->transferSourceId)
            ->orderBy('name');

        if ($this->transferMode === 'withdrawal') {
            $query->where('type', 'carteira');
        }

        $first = $query->value('id');
        $this->transferTargetId = $first !== null ? (string) $first : '';
    }

    public function closeTransfer(): void
    {
        $this->transferModalOpen = false;
        $this->transferSourceId = null;
        $this->transferTargetId = '';
        $this->transferAmount = 'R$ 0,00';
        $this->transferNotes = '';
        $this->transferMode = 'transfer';
        $this->resetValidation();
    }

    public function saveTransfer(TransferFunds $transfers): void
    {
        if (! $this->transferModalOpen || $this->transferSourceId === null) {
            return;
        }

        $userId = Auth::id();

        $data = $this->validate([
            'transferSourceId' => [
                'required', 'integer',
                Rule::exists('financial_accounts', 'id')->where('user_id', $userId)->where('active', true),
            ],
            'transferMode' => ['required', Rule::in(['transfer', 'withdrawal'])],
            'transferTargetId' => [
                'required', 'integer', 'different:transferSourceId',
                Rule::exists('financial_accounts', 'id')->where('user_id', $userId)->where('active', true),
            ],
            'transferAmount' => ['required', 'regex:/^R\$\s*(?:\d{1,3}(?:\.\d{3})+|\d+),\d{2}$/D'],
            'transferDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'transferNotes' => ['nullable', 'string', 'max:450'],
        ], [
            'transferTargetId.required' => 'Selecione uma conta de destino.',
            'transferTargetId.different' => 'A origem e o destino devem ser diferentes.',
            'transferAmount.required' => 'Informe o valor da movimentação.',
            'transferAmount.regex' => 'Informe o valor no formato R$ 1.234,56.',
            'transferDate.before_or_equal' => 'Registre somente transferências ou saques já realizados.',
        ]);

        $source = $this->ownedAccount((int) $data['transferSourceId']);
        $target = $this->ownedAccount((int) $data['transferTargetId']);

        if ($data['transferMode'] === 'withdrawal' && ($source->type === 'carteira' || $target->type !== 'carteira')) {
            throw ValidationException::withMessages([
                'transferTargetId' => 'Saques devem sair de uma conta bancária e entrar em uma Carteira.',
            ]);
        }

        $cents = (int) preg_replace('/\D/u', '', $data['transferAmount']);

        if ($cents <= 0 || $cents > 99999999999) {
            $this->addError('transferAmount', 'Informe um valor maior que zero e até R$ 999.999.999,99.');

            return;
        }

        $notes = trim($data['transferNotes'] ?? '');
        if ($data['transferMode'] === 'withdrawal') {
            $notes = 'Saque em espécie'.($notes !== '' ? ' — '.$notes : '');
        }

        $transfers->execute(
            (int) $userId,
            $source->id,
            $target->id,
            $cents,
            $data['transferDate'],
            $notes !== '' ? $notes : null,
            'transferAmount',
        );

        $isWithdrawal = $data['transferMode'] === 'withdrawal';
        $this->closeTransfer();
        session()->flash('success', $isWithdrawal
            ? 'Saque registrado: o valor saiu do banco e entrou na Carteira.'
            : 'Transferência registrada com sucesso. Os saldos das duas contas foram atualizados.');
    }


    public function openBillForm(?int $id = null): void
    {
        $this->resetValidation();
        $bill = $id !== null
            ? ScheduledBill::query()->where('user_id', Auth::id())->findOrFail($id)
            : null;

        if ($bill && $bill->daily_expense_id !== null) {
            $this->addError('billDescription', 'Uma conta paga não pode mais ser alterada.');

            return;
        }

        $this->editingBillId = $bill?->id;
        $this->billDescription = $bill?->description ?? '';
        $this->billCategory = $bill?->category ?? 'moradia';
        $this->billDueOn = $bill?->due_on?->toDateString() ?? now()->toDateString();
        $this->billAmount = $bill
            ? 'R$ '.FinancialBalance::formatted(FinancialBalance::cents($bill->amount))
            : 'R$ 0,00';
        $this->billNotes = $bill?->notes ?? '';
        $this->billFormOpen = true;
    }

    public function closeBillForm(): void
    {
        $this->billFormOpen = false;
        $this->editingBillId = null;
        $this->resetValidation();
    }

    private function moneyCents(string $value, string $field): int
    {
        if (! preg_match('/^R\$\s*(?:\d{1,3}(?:\.\d{3})+|\d+),\d{2}$/D', $value)) {
            throw ValidationException::withMessages([$field => 'Informe um valor como R$ 125,50.']);
        }

        $cents = (int) preg_replace('/\D/u', '', $value);
        if ($cents <= 0 || $cents > 99999999999) {
            throw ValidationException::withMessages([$field => 'O valor deve ser maior que zero e até R$ 999.999.999,99.']);
        }

        return $cents;
    }

    public function saveBill(): void
    {
        $data = $this->validate([
            'billDescription' => ['required', 'string', 'min:2', 'max:160'],
            'billCategory' => ['required', Rule::in(array_keys(\App\Livewire\DailyExpenses::CATEGORIES))],
            'billDueOn' => ['required', 'date_format:Y-m-d'],
            'billAmount' => ['required', 'string'],
            'billNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $cents = $this->moneyCents($data['billAmount'], 'billAmount');
        $values = [
            'description' => trim($data['billDescription']),
            'category' => $data['billCategory'],
            'due_on' => $data['billDueOn'],
            'amount' => number_format($cents / 100, 2, '.', ''),
            'notes' => trim($data['billNotes'] ?? '') ?: null,
        ];

        if ($this->editingBillId !== null) {
            $bill = ScheduledBill::query()->where('user_id', Auth::id())->findOrFail($this->editingBillId);
            if ($bill->daily_expense_id !== null) {
                throw ValidationException::withMessages(['billDescription' => 'A conta já foi paga e não pode ser alterada.']);
            }
            $bill->update($values);
            session()->flash('success', 'Conta a vencer atualizada sem movimentar o saldo.');
        } else {
            ScheduledBill::create(['user_id' => Auth::id(), ...$values]);
            session()->flash('success', 'Conta a vencer cadastrada. O saldo bancário não foi alterado.');
        }

        $this->closeBillForm();
    }

    public function removeBill(int $id): void
    {
        $bill = ScheduledBill::query()->where('user_id', Auth::id())->findOrFail($id);

        if ($bill->daily_expense_id !== null) {
            throw ValidationException::withMessages(['billDescription' => 'Não é possível remover uma conta já paga.']);
        }

        $bill->delete();
        session()->flash('success', 'Conta a vencer cancelada sem alterar os saldos.');
    }

    private function defaultPayAccountId(): string
    {
        $id = FinancialAccount::query()
            ->where('user_id', Auth::id())->where('active', true)
            ->orderBy('name')->value('id');

        return $id !== null ? (string) $id : '';
    }

    public function openBillPayment(int $id): void
    {
        $bill = ScheduledBill::query()->where('user_id', Auth::id())->findOrFail($id);

        if ($bill->daily_expense_id !== null) {
            $this->addError('billPaymentAccountId', 'A conta já está quitada.');

            return;
        }

        $this->resetValidation();
        $this->billToPayId = $id;
        $this->billPaymentAccountId = $this->defaultPayAccountId();
        $this->billPaidOn = now()->toDateString();
        $this->billPaymentModalOpen = true;
    }

    public function closeBillPayment(): void
    {
        $this->billPaymentModalOpen = false;
        $this->billToPayId = null;
        $this->resetValidation();
    }

    public function confirmBillPayment(): void
    {
        if (! $this->billPaymentModalOpen || $this->billToPayId === null) {
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
            (int) Auth::id(),
            $this->billToPayId,
            (int) $data['billPaymentAccountId'],
            $data['billPaidOn']
        );

        $this->closeBillPayment();
        session()->flash('success', 'Conta quitada. A saída foi registrada uma única vez no saldo e em Gastos.');
    }

    public function openCardPayment(int $invoiceId): void
    {
        CreditCardInvoice::query()
            ->where('user_id', Auth::id())
            ->whereHas('card', fn ($query) => $query->where('user_id', Auth::id()))
            ->findOrFail($invoiceId);

        $this->resetValidation();
        $this->cardInvoiceToPayId = $invoiceId;
        $this->cardPaymentAccountId = $this->defaultPayAccountId();
        $this->cardPaymentAmount = 'R$ 0,00';
        $this->cardPaymentOn = now()->toDateString();
        $this->cardInterestAmount = 'R$ 0,00';
        $this->cardInterestDescription = 'Juros/encargos informados pelo banco';
        $this->cardPaymentModalOpen = true;
    }

    public function closeCardPayment(): void
    {
        $this->cardPaymentModalOpen = false;
        $this->cardInvoiceToPayId = null;
        $this->resetValidation();
    }

    public function confirmCardPayment(): void
    {
        if (! $this->cardPaymentModalOpen || $this->cardInvoiceToPayId === null) {
            return;
        }

        $data = $this->validate([
            'cardPaymentAccountId' => [
                'required', 'integer',
                Rule::exists('financial_accounts', 'id')->where('user_id', Auth::id())->where('active', true),
            ],
            'cardPaymentAmount' => ['required', 'string'],
            'cardPaymentOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $amount = $this->moneyCents($data['cardPaymentAmount'], 'cardPaymentAmount');
        $result = app(PayCreditCardInvoice::class)->execute(
            (int) Auth::id(), $this->cardInvoiceToPayId,
            (int) $data['cardPaymentAccountId'], $amount, $data['cardPaymentOn']
        );

        $this->closeCardPayment();
        session()->flash('success', $result['remaining_cents'] > 0
            ? 'Pagamento parcial confirmado. O saldo restante foi transportado para a próxima fatura. Juros são lançados manualmente.'
            : 'Pagamento integral confirmado, descontado da conta bancária uma única vez.');
    }

    public function addCardInterest(): void
    {
        if (! $this->cardPaymentModalOpen || $this->cardInvoiceToPayId === null) {
            return;
        }

        $data = $this->validate([
            'cardInterestDescription' => ['required', 'string', 'min:3', 'max:160'],
            'cardInterestAmount' => ['required', 'string'],
        ]);

        $invoice = CreditCardInvoice::query()
            ->where('user_id', Auth::id())->findOrFail($this->cardInvoiceToPayId);

        $cents = $this->moneyCents($data['cardInterestAmount'], 'cardInterestAmount');

        CreditCardInvoiceCharge::create([
            'user_id' => Auth::id(),
            'credit_card_invoice_id' => $invoice->id,
            'description' => trim($data['cardInterestDescription']),
            'amount' => number_format($cents / 100, 2, '.', ''),
        ]);

        $this->cardInterestAmount = 'R$ 0,00';
        session()->flash('success', 'Encargo manual incluído na fatura selecionada.');
    }

    private function ownedAccount(int $id): FinancialAccount
    {
        return FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);
    }

    public function render()
    {
        $accounts = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('active')
            ->orderBy('name')
            ->withSum('dailyExpenses as expenses_paid', 'amount')
            ->withSum('receivedIncomes as income_received', 'amount')
            ->withSum('incomingTransfers as transfers_received', 'amount')
            ->withSum('outgoingTransfers as transfers_sent', 'amount')
            ->withSum('creditCardPayments as card_payments', 'amount')
            ->get();

        $activeAccounts = $accounts->where('active', true);
        $balanceCents = $activeAccounts->sum(fn ($account) => FinancialBalance::loadedCents($account));

        $modalSource = $this->transferSourceId !== null
            ? $accounts->firstWhere('id', $this->transferSourceId)
            : null;

        $transferDestinations = $accounts->filter(fn ($account) =>
            $account->active
            && $account->id !== $this->transferSourceId
            && ($this->transferMode !== 'withdrawal' || $account->type === 'carteira')
        );


        $bills = ScheduledBill::query()
            ->where('user_id', Auth::id())
            ->with('payment.financialAccount')
            ->orderBy('due_on')->orderBy('id')->limit(50)->get();

        $unpaidBills = ScheduledBill::query()
            ->where('user_id', Auth::id())
            ->whereNull('daily_expense_id')
            ->get();

        $billsPendingCents = $unpaidBills->sum(fn ($bill) => FinancialBalance::cents($bill->amount));

        $cardInvoices = collect();
        $cardDebtCents = 0;
        foreach (CreditCard::query()->where('user_id', Auth::id())->get() as $card) {
            $ledger = app(CreditCardLedger::class);
            $cardDebtCents += $ledger->totalDebt($card);
            foreach ($ledger->invoices($card) as $row) {
                if ($row['remaining_cents'] > 0) {
                    $cardInvoices->push(['card' => $card, ...$row]);
                }
            }
        }

        $cardInvoices = $cardInvoices
            ->sortBy(fn ($item) => $item['invoice']->due_on->format('Y-m-d'))
            ->take(24)->values();

        $billToPay = $this->billToPayId !== null
            ? $bills->firstWhere('id', $this->billToPayId)
            : null;
        $cardInvoiceToPay = $this->cardInvoiceToPayId !== null
            ? $cardInvoices->first(fn ($row) => $row['invoice']->id === $this->cardInvoiceToPayId)
            : null;

        return view('livewire.financial-accounts', [
            'bills' => $bills,
            'billsPendingCents' => $billsPendingCents,
            'cardInvoices' => $cardInvoices,
            'cardDebtCents' => $cardDebtCents,
            'billToPay' => $billToPay,
            'cardInvoiceToPay' => $cardInvoiceToPay,
            'categories' => AppLivewireDailyExpenses::CATEGORIES,
            'modalSource' => $modalSource,
            'modalSourceBalance' => $modalSource ? FinancialBalance::loadedCents($modalSource) : 0,
            'transferDestinations' => $transferDestinations,
            'accounts' => $accounts,
            'activeCount' => $activeAccounts->count(),
            'initialTotal' => number_format((float) $activeAccounts->sum('initial_balance'), 2, ',', '.'),
            'estimatedBalance' => number_format(abs($balanceCents) / 100, 2, ',', '.'),
            'isEstimatedDeficit' => $balanceCents < 0,
        ])->layout('layouts.app');
    }
}
