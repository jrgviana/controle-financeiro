<?php

namespace App\Livewire;

use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Support\FinancialBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class AccountTransfers extends Component
{
    use WithPagination;

    public string $fromAccountId = '';
    public string $toAccountId = '';
    public string $transferredOn = '';
    public string $amount = 'R$ 0,00';
    public string $notes = '';
    public string $selectedMonth = '';
    public string $filterAccountId = '';
    public int $formVersion = 0;

    public function mount(): void
    {
        $this->selectedMonth = now()->format('Y-m');
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $ids = FinancialAccount::query()
            ->where('user_id', Auth::id())
            ->where('active', true)
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->fromAccountId = $ids[0] ?? '';
        $this->toAccountId = $ids[1] ?? '';
        $this->transferredOn = now()->toDateString();
        $this->amount = 'R$ 0,00';
        $this->notes = '';
        $this->formVersion++;
    }

    public function updatedFromAccountId(): void
    {
        if ($this->toAccountId === $this->fromAccountId) {
            $destination = FinancialAccount::query()
                ->where('user_id', Auth::id())
                ->where('active', true)
                ->where('id', '!=', (int) $this->fromAccountId)
                ->orderBy('name')
                ->value('id');

            $this->toAccountId = $destination !== null ? (string) $destination : '';
        }
    }

    public function updatedSelectedMonth(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAccountId(): void
    {
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    public function save(): void
    {
        $userId = Auth::id();

        $data = $this->validate([
            'fromAccountId' => [
                'required',
                'integer',
                Rule::exists('financial_accounts', 'id')->where('user_id', $userId)->where('active', true),
            ],
            'toAccountId' => [
                'required',
                'integer',
                'different:fromAccountId',
                Rule::exists('financial_accounts', 'id')->where('user_id', $userId)->where('active', true),
            ],
            'transferredOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'regex:/^R\$\s*(?:\d{1,3}(?:\.\d{3})+|\d+),\d{2}$/D'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'fromAccountId.required' => 'Selecione a conta de origem.',
            'fromAccountId.exists' => 'A conta de origem não está disponível.',
            'toAccountId.required' => 'Selecione a conta de destino.',
            'toAccountId.different' => 'Escolha contas diferentes para origem e destino.',
            'toAccountId.exists' => 'A conta de destino não está disponível.',
            'transferredOn.before_or_equal' => 'Só é possível registrar transferências realizadas.',
            'amount.required' => 'Informe o valor transferido.',
            'amount.regex' => 'Informe o valor no formato R$ 1.234,56.',
        ]);

        // O campo já usa máscara e possui exatamente duas casas decimais.
        $digits = preg_replace('/\D/u', '', $data['amount']);
        $cents = (int) $digits;

        if ($cents <= 0 || $cents > 99999999999) {
            $this->addError('amount', 'Informe um valor maior que zero e de até R$ 999.999.999,99.');

            return;
        }

        $fromId = (int) $data['fromAccountId'];
        $toId = (int) $data['toAccountId'];

        app(\App\Services\TransferFunds::class)->execute(
            $userId,
            $fromId,
            $toId,
            $cents,
            $data['transferredOn'],
            trim($data['notes'] ?? '') ?: null,
        );

        $this->selectedMonth = substr($data['transferredOn'], 0, 7);
        $this->filterAccountId = '';
        $this->resetValidation();
        $this->resetForm();
        $this->resetPage();
        session()->flash('success', 'Transferência registrada. Os saldos das duas contas foram atualizados.');
    }

    public function undo(int $id): void
    {
        $userId = Auth::id();

        DB::transaction(function () use ($id, $userId): void {
            $transfer = AccountTransfer::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->findOrFail($id);

            $accounts = FinancialAccount::query()
                ->where('user_id', $userId)
                ->whereIn('id', [$transfer->from_account_id, $transfer->to_account_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $receiver = $accounts->get($transfer->to_account_id);

            if ($receiver === null) {
                throw ValidationException::withMessages([
                    'reversal' => 'A conta de destino não está disponível para estorno.',
                ]);
            }

            $balance = (new FinancialBalance())->currentCents($receiver);
            $amount = FinancialBalance::cents($transfer->amount);

            if ($balance < $amount) {
                throw ValidationException::withMessages([
                    'reversal' => 'Estorno bloqueado: o dinheiro já foi utilizado na conta de destino. Registre uma transferência de volta quando houver saldo.',
                ]);
            }

            // Exclusão lógica mantém o histórico, mas deixa de impactar o saldo.
            $transfer->delete();
        }, 3);

        $this->resetPage();
        session()->flash('success', 'Transferência estornada. Histórico preservado e saldos recalculados.');
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
            ->withSum('dailyExpenses as expenses_paid', 'amount')
            ->withSum('receivedIncomes as income_received', 'amount')
            ->withSum('incomingTransfers as transfers_received', 'amount')
            ->withSum('outgoingTransfers as transfers_sent', 'amount')
            ->get();

        $activeAccounts = $accounts->where('active', true);
        $source = $activeAccounts->firstWhere('id', (int) $this->fromAccountId);
        $sourceBalance = $source ? FinancialBalance::loadedCents($source) : 0;

        $accountIds = $accounts->pluck('id')->map(fn ($id) => (string) $id);
        $filterId = $accountIds->contains($this->filterAccountId) ? (int) $this->filterAccountId : null;

        $query = AccountTransfer::query()
            ->where('user_id', Auth::id())
            ->whereBetween('transferred_on', [$start, $end])
            ->when($filterId !== null, fn ($q) => $q->where(
                fn ($q) => $q->where('from_account_id', $filterId)
                    ->orWhere('to_account_id', $filterId)
            ));

        $monthTotal = (string) (clone $query)->sum('amount');
        $monthCount = (clone $query)->count();

        $history = $query->withTrashed()
            ->with(['fromAccount:id,name', 'toAccount:id,name'])
            ->orderByDesc('transferred_on')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.account-transfers', [
            'accounts' => $accounts,
            'activeAccounts' => $activeAccounts,
            'hasEnoughAccounts' => $activeAccounts->count() >= 2,
            'sourceBalance' => FinancialBalance::formatted($sourceBalance),
            'sourceIsDeficit' => $sourceBalance < 0,
            'monthTotal' => number_format((float) $monthTotal, 2, ',', '.'),
            'monthCount' => $monthCount,
            'history' => $history,
        ])->layout('layouts.app');
    }
}
