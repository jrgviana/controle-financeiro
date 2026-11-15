<?php

namespace App\Livewire;

use App\Models\FinancialAccount;
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

        return view('livewire.financial-accounts', [
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
