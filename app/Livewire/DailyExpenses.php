<?php

namespace App\Livewire;

use App\Models\DailyExpense;
use App\Models\CreditCard;
use App\Models\CreditCardPurchase;
use App\Services\RecordCreditCardPurchase;
use App\Support\FinancialBalance;
use App\Models\FinancialAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class DailyExpenses extends Component
{
    use WithPagination;

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

    public ?int $editingId = null;
    public int $formVersion = 0;
    public string $paidOn = '';
    public string $description = '';
    public string $category = 'outros';
    public string $accountId = '';
    public string $amount = 'R$ 0,00';
    public string $notes = '';
    public string $selectedMonth = '';
    public string $filterAccountId = '';
    public string $paymentMethod = 'account';
    public string $cardId = '';
    public int $installmentsCount = 1;
    public string $filterType = 'all';
    public string $filterCardId = '';

    public function mount(): void
    {
        $this->selectedMonth = now()->format('Y-m');
        $this->resetForm();
    }

    public function updatedSelectedMonth(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAccountId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCardId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentMethod(): void
    {
        if (! in_array($this->paymentMethod, ['account', 'card'], true)) {
            $this->paymentMethod = 'account';
        }

        if ($this->editingId !== null && $this->paymentMethod === 'card') {
            $this->cancel();
            $this->paymentMethod = 'card';
        }

        $this->resetValidation();
        $this->formVersion++;
    }

    private function resetForm(): void
    {
        $firstId = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->where('active', true)
            ->orderBy('name')
            ->value('id');

        $activeCardId = CreditCard::query()
            ->where('user_id', Auth::id())
            ->where('active', true)
            ->orderBy('name')
            ->value('id');

        $this->editingId = null;
        $this->cardId = $activeCardId !== null ? (string) $activeCardId : '';
        $this->installmentsCount = 1;
        $this->paidOn = now()->toDateString();
        $this->description = '';
        $this->category = 'outros';
        $this->accountId = $firstId !== null ? (string) $firstId : '';
        $this->amount = 'R$ 0,00';
        $this->notes = '';
        $this->formVersion++;
    }

    public function edit(int $id): void
    {
        $expense = $this->ownedExpense($id);
        $this->resetValidation();
        $this->paymentMethod = 'account';
        $this->editingId = $expense->id;
        $this->paidOn = $expense->paid_on->toDateString();
        $this->description = $expense->description;
        $this->category = $expense->category;
        $this->accountId = (string) $expense->financial_account_id;
        $this->amount = 'R$ '.number_format((float) $expense->amount, 2, ',', '.');
        $this->notes = $expense->notes ?? '';
        $this->formVersion++;
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    public function save(): void
    {
        if ($this->paymentMethod === 'card') {
            $this->saveCardPurchase();

            return;
        }

        $data = $this->validate([
            'paidOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'min:2', 'max:160'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'accountId' => ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('user_id', Auth::id())],
            'amount' => ['required', 'regex:/^(?:R\$\s*)?(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?$/D'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'paidOn.required' => 'Informe a data do pagamento.',
            'paidOn.before_or_equal' => 'Gastos futuros terão um módulo próprio de contas a vencer.',
            'description.required' => 'Informe a descrição do gasto.',
            'description.min' => 'A descrição deve ter pelo menos 2 caracteres.',
            'category.in' => 'Escolha uma categoria válida.',
            'accountId.required' => 'Selecione a conta de onde saiu o dinheiro.',
            'accountId.exists' => 'A conta selecionada não está disponível.',
            'amount.required' => 'Informe o valor do pagamento.',
            'amount.regex' => 'Informe um valor como R$ 25,50.',
        ]);

        $original = $this->editingId !== null ? $this->ownedExpense($this->editingId) : null;

        $account = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->findOrFail((int) $data['accountId']);

        if (! $account->active && ($original === null || $original->financial_account_id !== $account->id)) {
            $this->addError('accountId', 'Esta conta está desativada. Selecione uma conta ativa.');

            return;
        }

        $raw = preg_replace('/^R\$\s*/u', '', trim($data['amount']));
        $normalized = str_replace(',', '.', str_replace('.', '', $raw));
        $cents = (int) round((float) $normalized * 100);

        if ($cents <= 0 || $cents > 99999999999) {
            $this->addError('amount', 'O valor deve ser maior que zero e não ultrapassar R$ 999.999.999,99.');

            return;
        }

        $values = [
            'financial_account_id' => $account->id,
            'paid_on' => $data['paidOn'],
            'description' => trim($data['description']),
            'category' => $data['category'],
            'amount' => number_format($cents / 100, 2, '.', ''),
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];

        if ($original !== null) {
            $original->update($values);
            session()->flash('success', 'Gasto atualizado com sucesso.');
        } else {
            DailyExpense::create(['user_id' => Auth::id(), ...$values]);
            session()->flash('success', 'Gasto registrado com sucesso.');
        }

        $this->selectedMonth = substr($data['paidOn'], 0, 7);
        $this->filterAccountId = '';
        $this->resetValidation();
        $this->resetForm();
        $this->resetPage();
    }


    private function saveCardPurchase(): void
    {
        $data = $this->validate([
            'paymentMethod' => ['required', Rule::in(['card'])],
            'paidOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'min:2', 'max:160'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'cardId' => [
                'required',
                'integer',
                Rule::exists('credit_cards', 'id')->where('user_id', Auth::id())->where('active', true),
            ],
            'installmentsCount' => ['required', 'integer', 'between:1,60'],
            'amount' => ['required', 'regex:/^R\$\s*(?:\d{1,3}(?:\.\d{3})+|\d+),\d{2}$/D'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'paidOn.before_or_equal' => 'Informe a data em que a compra ocorreu.',
            'cardId.required' => 'Selecione o cartão utilizado.',
            'cardId.exists' => 'O cartão selecionado não está ativo ou não pertence à sua conta.',
            'installmentsCount.between' => 'Escolha de 1 a 60 parcelas.',
            'amount.regex' => 'Informe um valor como R$ 125,50.',
        ]);

        $cents = (int) preg_replace('/\D/u', '', $data['amount']);

        if ($cents <= 0 || $cents > 99999999999) {
            $this->addError('amount', 'O valor deve ser maior que zero e até R$ 999.999.999,99.');

            return;
        }

        $count = (int) $data['installmentsCount'];

        if ($count > $cents) {
            $this->addError('installmentsCount', 'Cada parcela deve ter pelo menos R$ 0,01.');

            return;
        }

        $selectedCardId = (string) $data['cardId'];
        app(RecordCreditCardPurchase::class)->execute(
            (int) Auth::id(),
            (int) $selectedCardId,
            $data['paidOn'],
            $data['description'],
            $data['category'],
            $cents,
            $count,
            $data['notes'] ?? null,
        );

        $this->selectedMonth = substr($data['paidOn'], 0, 7);
        $this->filterType = 'card';
        $this->filterCardId = $selectedCardId;
        $this->filterAccountId = '';
        $this->resetValidation();
        $this->resetForm();
        $this->cardId = $selectedCardId;
        $this->resetPage();
        session()->flash('success', $count === 1
            ? 'Compra no cartão registrada como 1 de 1. Confira a fatura na aba Cartões.'
            : 'Compra no cartão registrada: '.$count.' parcelas geradas nas faturas correspondentes.');
    }

    public function delete(int $id): void
    {
        $this->ownedExpense($id)->delete();

        if ($this->editingId === $id) {
            $this->cancel();
        }

        $this->resetPage();
        session()->flash('success', 'Gasto removido da listagem e dos totais. Seu registro foi preservado.');
    }

    private function ownedExpense(int $id): DailyExpense
    {
        return DailyExpense::query()->where('user_id', Auth::id())->findOrFail($id);
    }

    public function render()
    {
        $month = preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $this->selectedMonth)
            ? $this->selectedMonth
            : now()->format('Y-m');

        $start = $month.'-01';
        $end = Carbon::parse($start)->endOfMonth()->toDateString();

        $accounts = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $allowedIds = $accounts->pluck('id')->map(fn ($id) => (string) $id);
        $filterId = $allowedIds->contains($this->filterAccountId) ? (int) $this->filterAccountId : null;

        $cards = CreditCard::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $activeCards = $cards->where('active', true);
        $allowedCardIds = $cards->pluck('id')->map(fn ($id) => (string) $id);
        $cardFilterId = $allowedCardIds->contains($this->filterCardId)
            ? (int) $this->filterCardId
            : null;

        $paidQuery = DailyExpense::query()
            ->where('user_id', Auth::id())
            ->whereBetween('paid_on', [$start, $end])
            ->when($filterId !== null, fn ($query) => $query->where('financial_account_id', $filterId));

        $purchasesQuery = CreditCardPurchase::query()
            ->where('user_id', Auth::id())
            ->whereBetween('purchased_on', [$start, $end])
            ->when($cardFilterId !== null, fn ($query) => $query->where('credit_card_id', $cardFilterId));

        $paidTotalCents = FinancialBalance::cents((string) (clone $paidQuery)->sum('amount'));
        $cardTotalCents = FinancialBalance::cents((string) (clone $purchasesQuery)->sum('amount'));
        $paidCount = (clone $paidQuery)->count();
        $cardCount = (clone $purchasesQuery)->count();

        $paidRows = in_array($this->filterType, ['all', 'account'], true)
            ? $paidQuery->with('financialAccount:id,name')->get()->map(fn ($expense) => [
                'id' => $expense->id,
                'date' => $expense->paid_on,
                'description' => $expense->description,
                'category' => $expense->category,
                'origin' => $expense->financialAccount?->name ?? 'Conta indisponível',
                'amount' => $expense->amount,
                'notes' => $expense->notes,
                'kind' => 'account',
                'model' => $expense,
            ])
            : collect();

        $cardRows = in_array($this->filterType, ['all', 'card'], true)
            ? $purchasesQuery->with('card:id,name')->get()->map(fn ($purchase) => [
                'id' => $purchase->id,
                'date' => $purchase->purchased_on,
                'description' => $purchase->description,
                'category' => $purchase->category,
                'origin' => $purchase->card?->name ?? 'Cartão indisponível',
                'amount' => $purchase->amount,
                'notes' => $purchase->notes,
                'kind' => 'card',
                'model' => $purchase,
                'card_id' => $purchase->credit_card_id,
                'installments' => $purchase->installments_count,
            ])
            : collect();

        $rows = $paidRows->concat($cardRows)->sort(function ($a, $b) {
            $byDate = strcmp($b['date']->toDateString(), $a['date']->toDateString());

            if ($byDate !== 0) {
                return $byDate;
            }

            $byTime = ($b['model']->created_at?->timestamp ?? 0)
                <=> ($a['model']->created_at?->timestamp ?? 0);

            return $byTime !== 0 ? $byTime : ($b['id'] <=> $a['id']);
        })->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $movements = new LengthAwarePaginator(
            $rows->forPage($page, 15)->values(),
            $rows->count(),
            15,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        $editableAccounts = $accounts->filter(fn ($account) => $account->active || (
            $this->editingId !== null && (string) $account->id === $this->accountId
        ));

        return view('livewire.daily-expenses', [
            'accounts' => $accounts,
            'editableAccounts' => $editableAccounts,
            'hasActiveAccounts' => $accounts->contains('active', true),
            'categories' => self::CATEGORIES,
            'movements' => $movements,
            'cards' => $cards,
            'activeCards' => $activeCards,
            'hasActiveCards' => $activeCards->isNotEmpty(),
            'paidTotal' => FinancialBalance::formatted($paidTotalCents),
            'cardTotal' => FinancialBalance::formatted($cardTotalCents),
            'paidCount' => $paidCount,
            'cardCount' => $cardCount,
        ])->layout('layouts.app');
    }
}
