<?php

namespace App\Livewire;

use App\Models\CreditCard;
use App\Models\CreditCardInstallment;
use App\Models\CreditCardInvoice;
use App\Models\CreditCardInvoiceCharge;
use App\Models\CreditCardInvoicePayment;
use App\Models\CreditCardPurchase;
use App\Models\FinancialAccount;
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
        $first = CreditCard::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->first();

        $this->selectedCardId = $first !== null ? (string) $first->id : '';
        $this->selectedInvoiceId = $first !== null ? $this->preferredInvoiceId($first) : '';
        $this->cardFormOpen = $first === null;
        $this->resetPurchaseForm();
        $this->resetInterestForm();
        $this->resetPaymentForm();
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
        $this->resetValidation();
        $this->resetPurchaseForm();
        $this->resetInterestForm();
        $this->resetPaymentForm();
    }

    public function selectInvoice(int $id): void
    {
        $this->ownedInvoice($id);
        $this->selectedInvoiceId = (string) $id;
        $this->resetValidation();
        $this->resetInterestForm();
        $this->resetPaymentForm();
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

    public function savePurchase(): void
    {
        $cycle = app(CreditCardCycle::class);
        $card = $this->selectedCard();

        if (! $card->active) {
            $this->addError('purchaseDescription', 'Este cartão está desativado para novas compras.');

            return;
        }

        $data = $this->validate([
            'purchaseDescription' => ['required', 'string', 'min:2', 'max:160'],
            'purchaseOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'purchaseAmount' => ['required', 'string'],
            'installmentsCount' => ['required', 'integer', 'between:1,60'],
            'purchaseCategory' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'purchaseNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'purchaseDescription.required' => 'Informe o que foi comprado.',
            'purchaseOn.before_or_equal' => 'A compra precisa já ter sido realizada.',
            'installmentsCount.between' => 'Escolha entre 1 e 60 parcelas.',
        ]);

        $cents = $this->moneyCents($data['purchaseAmount'], 'purchaseAmount');
        $count = (int) $data['installmentsCount'];
        $firstDue = $cycle->firstDue($card, $data['purchaseOn']);

        $firstInvoice = DB::transaction(function () use ($card, $data, $cycle, $cents, $count, $firstDue): int {
            $lockedCard = CreditCard::query()
                ->where('user_id', Auth::id())
                ->lockForUpdate()
                ->findOrFail($card->id);

            if (! $lockedCard->active) {
                throw ValidationException::withMessages([
                    'purchaseDescription' => 'Este cartão está desativado para novas compras.',
                ]);
            }

            $purchase = CreditCardPurchase::create([
                'user_id' => Auth::id(),
                'credit_card_id' => $card->id,
                'purchased_on' => $data['purchaseOn'],
                'description' => trim($data['purchaseDescription']),
                'category' => $data['purchaseCategory'],
                'amount' => $this->decimal($cents),
                'installments_count' => $count,
                'notes' => trim($data['purchaseNotes'] ?? '') ?: null,
            ]);

            $each = intdiv($cents, $count);
            $remainder = $cents % $count;
            $firstId = 0;

            for ($i = 0; $i < $count; $i++) {
                $due = $cycle->dueForInstallment($card, $firstDue, $i);
                $invoice = CreditCardInvoice::forDue($lockedCard, $due->toDateString());

                if ($i === 0) {
                    $firstId = $invoice->id;
                }

                CreditCardInstallment::create([
                    'user_id' => Auth::id(),
                    'credit_card_purchase_id' => $purchase->id,
                    'credit_card_invoice_id' => $invoice->id,
                    'installment_number' => $i + 1,
                    'amount' => $this->decimal($each + ($i === $count - 1 ? $remainder : 0)),
                ]);
            }

            return $firstId;
        }, 3);

        $this->selectedInvoiceId = (string) $firstInvoice;
        $this->resetValidation();
        $this->resetPurchaseForm();
        session()->flash('success', 'Compra lançada e '.($count === 1 ? 'parcela 1/1 gerada.' : $count.' parcelas distribuídas nas faturas.'));
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

            $laterPaid = CreditCardInvoicePayment::query()
                ->where('user_id', Auth::id())
                ->whereHas('invoice', fn ($query) => $query
                    ->where('credit_card_id', $cardId)
                    ->whereDate('due_on', '>', $lockedInvoice->due_on->toDateString()))
                ->exists();

            if ($laterPaid) {
                throw ValidationException::withMessages([
                    'paymentAmount' => 'Há faturas posteriores com pagamento registrado. Pague a fatura mais recente para preservar o saldo transportado.',
                ]);
            }

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
        $invoices = $card ? $ledger->invoices($card) : collect();

        $selected = $invoices->first(
            fn ($row) => $row['invoice']->id === (int) $this->selectedInvoiceId
        ) ?? $invoices->first(fn ($row) => $row['remaining_cents'] > 0)
          ?? $invoices->last();

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

        $recentPurchases = $card
            ? CreditCardPurchase::query()
                ->where('user_id', Auth::id())
                ->where('credit_card_id', $card->id)
                ->orderByDesc('purchased_on')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
            : collect();

        return view('livewire.credit-cards', [
            'cards' => $cards,
            'card' => $card,
            'summaries' => $summary,
            'invoices' => $invoices,
            'selectedInvoice' => $selected,
            'accounts' => $accounts,
            'categories' => self::CATEGORIES,
            'recentPurchases' => $recentPurchases,
            'totalDebtCents' => $summary->sum('debt_cents'),
        ])->layout('layouts.app');
    }
}
