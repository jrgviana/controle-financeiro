<?php

namespace App\Livewire;

use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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

    public bool $formOpen = false;
    public ?int $editingId = null;
    public string $paidOn = '';
    public string $description = '';
    public string $category = 'outros';
    public string $accountId = '';
    public string $amount = 'R$ 0,00';
    public string $notes = '';
    public string $selectedMonth = '';
    public string $filterAccountId = '';

    public function mount(): void
    {
        $this->selectedMonth = now()->format('Y-m');
        $this->paidOn = now()->toDateString();
    }

    public function updatedSelectedMonth(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAccountId(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $firstId = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->where('active', true)
            ->orderBy('name')
            ->value('id');

        if ($firstId === null) {
            session()->flash('error', 'Cadastre ou reative uma conta antes de lançar um gasto.');

            return;
        }

        $this->resetValidation();
        $this->editingId = null;
        $this->paidOn = now()->toDateString();
        $this->description = '';
        $this->category = 'outros';
        $this->accountId = (string) $firstId;
        $this->amount = 'R$ 0,00';
        $this->notes = '';
        $this->formOpen = true;
    }

    public function edit(int $id): void
    {
        $expense = $this->ownedExpense($id);
        $this->resetValidation();
        $this->editingId = $expense->id;
        $this->paidOn = $expense->paid_on->toDateString();
        $this->description = $expense->description;
        $this->category = $expense->category;
        $this->accountId = (string) $expense->financial_account_id;
        $this->amount = 'R$ '.number_format((float) $expense->amount, 2, ',', '.');
        $this->notes = $expense->notes ?? '';
        $this->formOpen = true;
    }

    public function cancel(): void
    {
        $this->formOpen = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save(): void
    {
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

        $this->formOpen = false;
        $this->editingId = null;
        $this->resetPage();
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

        $query = DailyExpense::query()
            ->where('user_id', Auth::id())
            ->whereBetween('paid_on', [$start, $end])
            ->when($filterId !== null, fn ($query) => $query->where('financial_account_id', $filterId));

        $total = (float) (clone $query)->sum('amount');
        $count = (clone $query)->count();

        $expenses = $query->with('financialAccount:id,name')
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate(15);

        $editableAccounts = $accounts->filter(fn ($account) => $account->active || (
            $this->editingId !== null && (string) $account->id === $this->accountId
        ));

        return view('livewire.daily-expenses', [
            'accounts' => $accounts,
            'editableAccounts' => $editableAccounts,
            'hasActiveAccounts' => $accounts->contains('active', true),
            'categories' => self::CATEGORIES,
            'expenses' => $expenses,
            'monthTotal' => number_format($total, 2, ',', '.'),
            'monthCount' => $count,
        ])->layout('layouts.app');
    }
}
