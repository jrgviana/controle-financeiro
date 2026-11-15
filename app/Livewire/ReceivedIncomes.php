<?php

namespace App\Livewire;

use App\Models\FinancialAccount;
use App\Models\ReceivedIncome;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ReceivedIncomes extends Component
{
    use WithPagination;

    public const CATEGORIES = [
        'salario' => 'Salário',
        'extra' => 'Renda extra',
        'reembolso' => 'Reembolso',
        'venda' => 'Venda',
        'presente' => 'Presente',
        'outros' => 'Outros',
    ];

    public ?int $editingId = null;
    public int $formVersion = 0;
    public string $receivedOn = '';
    public string $description = '';
    public string $category = 'salario';
    public string $accountId = '';
    public string $amount = 'R$ 0,00';
    public string $notes = '';
    public string $selectedMonth = '';
    public string $filterAccountId = '';

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

    private function resetForm(): void
    {
        $firstId = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->where('active', true)
            ->orderBy('name')
            ->value('id');

        $this->editingId = null;
        $this->receivedOn = now()->toDateString();
        $this->description = '';
        $this->category = 'salario';
        $this->accountId = $firstId !== null ? (string) $firstId : '';
        $this->amount = 'R$ 0,00';
        $this->notes = '';
        $this->formVersion++;
    }

    public function edit(int $id): void
    {
        $income = $this->ownedIncome($id);

        $this->resetValidation();
        $this->editingId = $income->id;
        $this->receivedOn = $income->received_on->toDateString();
        $this->description = $income->description;
        $this->category = $income->category;
        $this->accountId = (string) $income->financial_account_id;
        $this->amount = 'R$ '.number_format((float) $income->amount, 2, ',', '.');
        $this->notes = $income->notes ?? '';
        $this->formVersion++;
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    public function save(): void
    {
        $data = $this->validate([
            'receivedOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'min:2', 'max:160'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'accountId' => ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('user_id', Auth::id())],
            'amount' => ['required', 'regex:/^(?:R\$\s*)?(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?$/D'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'receivedOn.required' => 'Informe a data do recebimento.',
            'receivedOn.before_or_equal' => 'Registre somente dinheiro que já entrou na conta.',
            'description.required' => 'Informe a descrição do recebimento.',
            'description.min' => 'A descrição deve ter pelo menos 2 caracteres.',
            'category.in' => 'Escolha uma categoria válida.',
            'accountId.required' => 'Informe a conta que recebeu o dinheiro.',
            'accountId.exists' => 'A conta selecionada não está disponível.',
            'amount.required' => 'Informe o valor recebido.',
            'amount.regex' => 'Informe um valor como R$ 1.250,50.',
        ]);

        $original = $this->editingId !== null ? $this->ownedIncome($this->editingId) : null;

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
            'received_on' => $data['receivedOn'],
            'description' => trim($data['description']),
            'category' => $data['category'],
            'amount' => number_format($cents / 100, 2, '.', ''),
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];

        if ($original !== null) {
            $original->update($values);
            session()->flash('success', 'Recebimento atualizado com sucesso.');
        } else {
            ReceivedIncome::create(['user_id' => Auth::id(), ...$values]);
            session()->flash('success', 'Recebimento registrado com sucesso.');
        }

        $this->selectedMonth = substr($data['receivedOn'], 0, 7);
        $this->filterAccountId = '';
        $this->resetValidation();
        $this->resetForm();
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $this->ownedIncome($id)->delete();

        if ($this->editingId === $id) {
            $this->cancel();
        }

        $this->resetPage();
        session()->flash('success', 'Recebimento removido dos totais. Seu registro foi preservado.');
    }

    private function ownedIncome(int $id): ReceivedIncome
    {
        return ReceivedIncome::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);
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

        $query = ReceivedIncome::query()
            ->where('user_id', Auth::id())
            ->whereBetween('received_on', [$start, $end])
            ->when($filterId !== null, fn ($query) => $query->where('financial_account_id', $filterId));

        $total = (float) (clone $query)->sum('amount');
        $count = (clone $query)->count();

        $incomes = $query
            ->with('financialAccount:id,name')
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->paginate(15);

        $editableAccounts = $accounts->filter(fn ($account) => $account->active || (
            $this->editingId !== null && (string) $account->id === $this->accountId
        ));

        return view('livewire.received-incomes', [
            'accounts' => $accounts,
            'editableAccounts' => $editableAccounts,
            'hasActiveAccounts' => $accounts->contains('active', true),
            'categories' => self::CATEGORIES,
            'incomes' => $incomes,
            'monthTotal' => number_format($total, 2, ',', '.'),
            'monthCount' => $count,
        ])->layout('layouts.app');
    }
}
