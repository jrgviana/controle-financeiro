<?php

namespace App\Livewire;

use App\Models\FinancialAccount;
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

    public string $initialBalance = '0,00';

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->type = 'corrente';
        $this->institution = '';
        $this->initialBalance = '0,00';
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
        $this->initialBalance = number_format((float) $account->initial_balance, 2, ',', '.');
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
            'initialBalance' => ['required', 'regex:/^(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?$/D'],
        ], [
            'name.required' => 'Informe o nome da conta.',
            'name.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'type.in' => 'Escolha um tipo de conta válido.',
            'initialBalance.required' => 'Informe o saldo inicial, mesmo que seja zero.',
            'initialBalance.regex' => 'Informe um valor válido, como 0,00 ou 1.250,50.',
        ]);

        $normalized = str_replace(',', '.', str_replace('.', '', $data['initialBalance']));

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
            ->get();

        $activeAccounts = $accounts->where('active', true);

        return view('livewire.financial-accounts', [
            'accounts' => $accounts,
            'activeCount' => $activeAccounts->count(),
            'initialTotal' => number_format((float) $activeAccounts->sum('initial_balance'), 2, ',', '.'),
        ])->layout('layouts.app');
    }
}
