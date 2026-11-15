<?php

namespace App\Livewire;

use App\Models\CreditCard;
use App\Models\CreditCardInstallment;
use App\Models\CreditCardInvoice;
use App\Models\CreditCardInvoiceCharge;
use App\Models\CreditCardInvoicePayment;
use App\Models\FinancialAccount;
use App\Services\ScheduleCreditCardInstallments;
use App\Support\CreditCardCycle;
use App\Support\CreditCardLedger;
use App\Support\FinancialBalance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CreditCards extends Component
{
    public const CATEGORIES = [
        'alimentacao' => 'Alimentação',
        'transporte' => 'Transporte',
        'moradia' => 'Moradia',
        'saude' => 'Saúde',
        'compras' => 'Compras',
        'lazer' => 'Lazer',
        'educacao' => 'Educação',
        'outros' => 'Outros',
    ];

    public string $selectedCardId = '';
    public string $selectedInvoiceId = '';
    public bool $invoiceModalOpen = false;
    public array $advanceInstallmentIds = [];
    public string $advanceTargetMonth = '';

    public bool $cardFormOpen = false;
    public ?int $editingCardId = null;
    public string $cardName = '';
    public string $cardIssuer = '';
    public string $cardBrand = '';
    public string $cardLimit = '';
    public string $closingDay = '10';
    public string $dueDay = '15';

    public string $purchaseDescription = '';
    public string $purchaseOn = '';
    public string $purchaseAmount = 'R$ 0,00';
    public string $purchaseCategory = 'compras';
    public int $installmentsCount = 1;
    public string $purchaseNotes = '';
    public int $purchaseFormVersion = 0;

    public string $interestDescription = 'Juros/encargos da fatura anterior';
    public string $interestAmount = 'R$ 0,00';
    public int $interestFormVersion = 0;

    public string $paymentAccountId = '';
    public string $paymentAmount = 'R$ 0,00';
    public string $paymentOn = '';
    public int $paymentFormVersion = 0;

    public function mount(): void
    {
        $selected = filter_var(request()->query('cartao'), FILTER_VALIDATE_INT);
        $first = $selected
            ? CreditCard::query()->where('user_id', Auth::id())->whereKey($selected)->first()
            : null;

        $first ??= CreditCard::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->first();

        $this->selectedCardId = $first !== null ? (string) $first->id : '';
        $this->selectedInvoiceId = $first !== null ? $this->preferredInvoiceId($first) : '';
        $linkedInvoice = filter_var(request()->query('fatura'), FILTER_VALIDATE_INT);
        if ($first !== null && $linkedInvoice) {
            $matching = CreditCardInvoice::query()
                ->where('user_id', Auth::id())
                ->where('credit_card_id', $first->id)
                ->find($linkedInvoice);

            if ($matching !== null) {
                $this->selectedInvoiceId = (string) $matching->id;
                $this->invoiceModalOpen = true;
            }
        }
        $this->cardFormOpen = $first === null;
        $this->resetPurchaseForm();
        $this->resetInterestForm();
        $this->resetPaymentForm();
        $this->resetAdvanceForm();
    }

    public function openCardForm(): void
    {
        $this->resetValidation();
        $this->editingCardId = null;
        $this->cardName = '';
        $this->cardIssuer = '';
        $this->cardBrand = '';
        $this->cardLimit = '';
        $this->closingDay = '10';
        $this->dueDay = '15';
        $this->cardFormOpen = true;
    }

    public function editCard(int $id): void
    {
        $card = $this->ownedCard($id);
        $this->resetValidation();
        $this->editingCardId = $id;
        $this->cardName = $card->name;
        $this->cardIssuer = $card->issuer ?? '';
        $this->cardBrand = $card->brand ?? '';
        $this->cardLimit = $card->credit_limit !== null
            ? 'R$ '.FinancialBalance::formatted(FinancialBalance::cents($card->credit_limit))
            : '';
        $this->closingDay = (string) $card->closing_day;
        $this->dueDay = (string) $card->due_day;
        $this->cardFormOpen = true;
    }

    public function cancelCardForm(): void
    {
        $this->resetValidation();
        $this->cardFormOpen = false;
        $this->editingCardId = null;
    }

    private function moneyCents(string $input, string $field): int
    {
        if (! preg_match('/^R\$\s*(?:\d{1,3}(?:\.\d{3})+|\d+),\d{2}$/D', $input)) {
            throw ValidationException::withMessages([
                $field => 'Informe um valor como R$ 1.234,56.',
            ]);
        }

        $cents = (int) preg_replace('/\D/u', '', $input);

        if ($cents <= 0 || $cents > 99999999999) {
            throw ValidationException::withMessages([
                $field => 'O valor deve ser maior que zero e até R$ 999.999.999,99.',
            ]);
        }

        return $cents;
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public function saveCard(): void
    {
        $data = $this->validate([
            'cardName' => ['required', 'string', 'min:2', 'max:100'],
            'cardIssuer' => ['nullable', 'string', 'max:100'],
            'cardBrand' => ['nullable', 'string', 'max:50'],
            'cardLimit' => ['nullable', 'regex:/^R\$\s*(?:\d{1,3}(?:\.\d{3})+|\d+),\d{2}$/D'],
            'closingDay' => ['required', 'integer', 'between:1,31'],
            'dueDay' => ['required', 'integer', 'between:1,31'],
        ], [
            'cardName.required' => 'Informe o nome do cartão.',
            'closingDay.between' => 'Informe um dia de fechamento de 1 a 31.',
            'dueDay.between' => 'Informe um dia de vencimento de 1 a 31.',
        ]);

        $limit = trim($data['cardLimit'] ?? '') !== ''
            ? $this->decimal($this->moneyCents($data['cardLimit'], 'cardLimit'))
            : null;

        $values = [
            'name' => trim($data['cardName']),
            'issuer' => trim($data['cardIssuer'] ?? '') ?: null,
            'brand' => trim($data['cardBrand'] ?? '') ?: null,
            'credit_limit' => $limit,
            'closing_day' => (int) $data['closingDay'],
            'due_day' => (int) $data['dueDay'],
        ];

        if ($this->editingCardId !== null) {
            $card = $this->ownedCard($this->editingCardId);
            $card->update($values);
            session()->flash('success', 'Cartão atualizado. As parcelas já geradas mantêm seus vencimentos.');
        } else {
            $card = CreditCard::create(['user_id' => Auth::id(), ...$values]);
            session()->flash('success', 'Cartão cadastrado com sucesso.');
        }

        $this->selectedCardId = (string) $card->id;
        $this->selectedInvoiceId = $this->preferredInvoiceId($card);
        $this->cardFormOpen = false;
        $this->editingCardId = null;
        $this->resetValidation();
    }

    public function toggleCard(int $id): void
    {
        $card = $this->ownedCard($id);
        $card->update(['active' => ! $card->active]);

        session()->flash('success', $card->active
            ? 'Cartão reativado.'
            : 'Cartão desativado para novas compras. As faturas e parcelas permanecem.');
    }

    private function preferredInvoiceId(CreditCard $card): string
    {
        $rows = app(CreditCardLedger::class)->invoices($card);
        $row = $rows->first(fn ($entry) => $entry['remaining_cents'] > 0) ?? $rows->last();

        return $row !== null ? (string) $row['invoice']->id : '';
    }

    public function selectCard(int $id): void
    {
        $card = $this->ownedCard($id);
        $this->selectedCardId = (string) $id;
        $this->selectedInvoiceId = $this->preferredInvoiceId($card);
        $this->invoiceModalOpen = true;
        $this->resetValidation();
        $this->resetPurchaseForm();
        $this->resetInterestForm();
        $this->resetPaymentForm();
        $this->resetAdvanceForm();
    }

    public function closeInvoices(): void
    {
        $this->invoiceModalOpen = false;
        $this->advanceInstallmentIds = [];
    }

    public function selectInvoice(int $id): void
    {
        $this->ownedInvoice($id);
        $this->selectedInvoiceId = (string) $id;
        $this->resetValidation();
        $this->resetInterestForm();
        $this->resetPaymentForm();
        $this->resetAdvanceForm();
    }

    private function ownedCard(int $id): CreditCard
    {
        return CreditCard::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);
    }

    private function selectedCard(): CreditCard
    {
        return $this->ownedCard((int) $this->selectedCardId);
    }

    private function ownedInvoice(int $id): CreditCardInvoice
    {
        return CreditCardInvoice::query()
            ->where('user_id', Auth::id())
            ->where('credit_card_id', (int) $this->selectedCardId)
            ->findOrFail($id);
    }

    private function resetPurchaseForm(): void
    {
        $this->purchaseDescription = '';
        $this->purchaseOn = now()->toDateString();
        $this->purchaseAmount = 'R$ 0,00';
        $this->purchaseCategory = 'compras';
        $this->installmentsCount = 1;
        $this->purchaseNotes = '';
        $this->purchaseFormVersion++;
    }

    public function clearPurchase(): void
    {
        $this->resetValidation();
        $this->resetPurchaseForm();
    }

    // Mantido para compatibilidade com lançamentos já integrados;
    // na interface, a entrada de novas compras está somente em Gastos.
    public function savePurchase(): void
    {
        $card = $this->selectedCard();

        $data = $this->validate([
            'purchaseDescription' => ['required', 'string', 'min:2', 'max:160'],
            'purchaseOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'purchaseAmount' => ['required', 'string'],
            'installmentsCount' => ['required', 'integer', 'between:1,60'],
            'purchaseCategory' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'purchaseNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $cents = $this->moneyCents($data['purchaseAmount'], 'purchaseAmount');

        app(\App\Services\RecordCreditCardPurchase::class)->execute(
            (int) Auth::id(),
            $card->id,
            $data['purchaseOn'],
            $data['purchaseDescription'],
            $data['purchaseCategory'],
            $cents,
            (int) $data['installmentsCount'],
            $data['purchaseNotes'] ?? null,
        );

        $this->selectedInvoiceId = $this->preferredInvoiceId($card);
        $this->invoiceModalOpen = true;
        $count = (int) $data['installmentsCount'];
        $this->resetValidation();
        $this->resetPurchaseForm();
        session()->flash('success', $count === 1
            ? 'Compra lançada e parcela 1/1 gerada.'
            : $count.' parcelas distribuídas nas faturas.');
    }

    private function resetInterestForm(): void
    {
        $this->interestDescription = 'Juros/encargos da fatura anterior';
        $this->interestAmount = 'R$ 0,00';
        $this->interestFormVersion++;
    }

    public function addInterest(): void
    {
        $invoice = $this->ownedInvoice((int) $this->selectedInvoiceId);

        $data = $this->validate([
            'interestDescription' => ['required', 'string', 'min:3', 'max:160'],
            'interestAmount' => ['required', 'string'],
        ]);

        $cents = $this->moneyCents($data['interestAmount'], 'interestAmount');

        CreditCardInvoiceCharge::create([
            'user_id' => Auth::id(),
            'credit_card_invoice_id' => $invoice->id,
            'description' => trim($data['interestDescription']),
            'amount' => $this->decimal($cents),
        ]);

        $this->resetValidation();
        $this->resetInterestForm();
        session()->flash('success', 'Juros/encargos adicionados manualmente à fatura selecionada.');
    }

    private function resetAdvanceForm(): void
    {
        $this->advanceInstallmentIds = [];
        $this->advanceTargetMonth = now()->format('Y-m');
    }

    public function advanceSelectedInstallments(): void
    {
        if (! $this->invoiceModalOpen) {
            return;
        }

        $invoice = $this->ownedInvoice((int) $this->selectedInvoiceId);

        $data = $this->validate([
            'advanceInstallmentIds' => ['required', 'array', 'min:1'],
            'advanceInstallmentIds.*' => ['required', 'integer', 'distinct'],
            'advanceTargetMonth' => ['required', 'date_format:Y-m'],
        ], [
            'advanceInstallmentIds.required' => 'Marque ao menos uma parcela.',
            'advanceTargetMonth.required' => 'Escolha o mês para o qual deseja trazer essas parcelas.',
        ]);

        $result = app(ScheduleCreditCardInstallments::class)->execute(
            (int) Auth::id(),
            $invoice->id,
            $data['advanceInstallmentIds'],
            $data['advanceTargetMonth']
        );

        $this->selectedInvoiceId = (string) $result['target_invoice_id'];
        $this->resetValidation();
        $this->resetAdvanceForm();

        session()->flash('success',
            $result['count'].' parcela(s) programada(s) para '
            .$result['target_month'].'. Confira em Contas fixas. Nenhum valor foi descontado do banco.'
        );
    }

    public function undoPlannedInstallment(int $installmentId): void
    {
        if (! $this->invoiceModalOpen) {
            return;
        }

        app(ScheduleCreditCardInstallments::class)->undo((int) Auth::id(), $installmentId);
        $this->resetValidation();
        $this->resetAdvanceForm();
        session()->flash('success', 'Antecipação programada desfeita. A parcela voltou ao vencimento original.');
    }

    private function resetPaymentForm(): void
    {
        $id = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->where('active', true)
            ->orderBy('name')
            ->value('id');

        $this->paymentAccountId = $id !== null ? (string) $id : '';
        $this->paymentOn = now()->toDateString();
        $this->paymentAmount = 'R$ 0,00';
        $this->paymentFormVersion++;
    }

    public function payInvoice(): void
    {
        $cycle = app(CreditCardCycle::class);
        $ledger = app(CreditCardLedger::class);
        $invoice = $this->ownedInvoice((int) $this->selectedInvoiceId);
        $card = $this->selectedCard();

        $data = $this->validate([
            'paymentAccountId' => [
                'required', 'integer',
                Rule::exists('financial_accounts', 'id')->where('user_id', Auth::id())->where('active', true),
            ],
            'paymentAmount' => ['required', 'string'],
            'paymentOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'paymentAccountId.required' => 'Escolha a conta bancária que fez o pagamento.',
            'paymentOn.before_or_equal' => 'Registre somente pagamentos já realizados.',
        ]);

        $cents = $this->moneyCents($data['paymentAmount'], 'paymentAmount');
        $invoiceId = $invoice->id;
        $cardId = $card->id;

        $nextInvoiceId = DB::transaction(function () use ($cycle, $ledger, $cardId, $invoiceId, $data, $cents): int {
            $lockedCard = CreditCard::query()
                ->where('user_id', Auth::id())
                ->lockForUpdate()
                ->findOrFail($cardId);

            $lockedInvoice = CreditCardInvoice::query()
                ->where('user_id', Auth::id())
                ->where('credit_card_id', $cardId)
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            $account = FinancialAccount::query()
                ->where('user_id', Auth::id())
                ->where('active', true)
                ->lockForUpdate()
                ->findOrFail((int) $data['paymentAccountId']);

            $row = $ledger->invoices($lockedCard)->first(
                fn ($entry) => $entry['invoice']->id === $lockedInvoice->id
            );

            if ($row === null || $row['remaining_cents'] < $cents) {
                throw ValidationException::withMessages([
                    'paymentAmount' => 'O pagamento ultrapassa o valor em aberto da fatura.',
                ]);
            }

            $available = (new FinancialBalance())->currentCents($account);

            if ($available < $cents) {
                throw ValidationException::withMessages([
                    'paymentAmount' => 'Saldo insuficiente na conta pagadora. Disponível: R$ '.FinancialBalance::formatted(max(0, $available)).'.',
                ]);
            }

            CreditCardInvoicePayment::create([
                'user_id' => Auth::id(),
                'credit_card_invoice_id' => $invoiceId,
                'financial_account_id' => $account->id,
                'paid_on' => $data['paymentOn'],
                'amount' => $this->decimal($cents),
            ]);

            $remaining = $row['remaining_cents'] - $cents;

            if ($remaining > 0) {
                $nextDue = $cycle->nextDue($lockedCard, $lockedInvoice->due_on->toDateString());

                $nextInvoice = CreditCardInvoice::forDue($lockedCard, $nextDue->toDateString());

                return $nextInvoice->id;
            }

            return $invoiceId;
        }, 3);

        $this->selectedInvoiceId = (string) $nextInvoiceId;
        $this->resetValidation();
        $this->resetPaymentForm();
        session()->flash('success', $nextInvoiceId !== $invoiceId
            ? 'Pagamento parcial registrado. O saldo restante está projetado na próxima fatura; lance os juros manualmente quando forem informados pelo banco.'
            : 'Pagamento registrado. A fatura foi atualizada.');
    }

    public function render()
    {
        $cycle = app(CreditCardCycle::class);
        $ledger = app(CreditCardLedger::class);
        $cards = CreditCard::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $card = $cards->firstWhere('id', (int) $this->selectedCardId) ?? $cards->first();
        $invoices = $card ? $ledger->invoices($card)->filter(fn ($row) =>
            $row['installments_cents'] > 0 || $row['interest_cents'] > 0
            || $row['paid_cents'] > 0 || $row['carry_cents'] > 0
        )->values() : collect();

        $selected = $invoices->first(
            fn ($row) => $row['invoice']->id === (int) $this->selectedInvoiceId
        ) ?? $invoices->first(fn ($row) => $row['remaining_cents'] > 0)
          ?? $invoices->last();

        $advanceSelectable = $selected !== null
            && $selected['invoice']->due_on->startOfDay()->isFuture()
            && $selected['remaining_cents'] > 0
            && $selected['invoice']->payments->isEmpty();

        $advanceOptions = $advanceSelectable
            ? $selected['invoice']->installments->filter(fn ($row) =>
                $row->advance === null && $row->scheduledPlan === null
            )
            : collect();

        $selectedAdvanceIds = array_map('intval', $this->advanceInstallmentIds);
        $advanceSelectedCents = $advanceOptions
            ->filter(fn ($row) => in_array($row->id, $selectedAdvanceIds, true))
            ->sum(fn ($row) => FinancialBalance::cents($row->amount));

        $summary = $cards->map(fn ($item) => [
            'card' => $item,
            'debt_cents' => $ledger->totalDebt($item),
            'best_on' => $cycle->nextBestPurchaseDate($item),
        ]);

        $accounts = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.credit-cards', [
            'cards' => $cards,
            'card' => $card,
            'summaries' => $summary,
            'invoices' => $invoices,
            'selectedInvoice' => $selected,
            'advanceSelectable' => $advanceSelectable,
            'advanceSelectedCents' => $advanceSelectedCents,
            'advanceOptions' => $advanceOptions,
            'accounts' => $accounts,
            'categories' => self::CATEGORIES,
            'totalDebtCents' => $summary->sum('debt_cents'),
        ])->layout('layouts.app');
    }
}
