<?php

namespace App\Livewire;

use App\Models\CreditCardInvoice;
use App\Models\FinancialAccount;
use App\Models\RecurringBill;
use App\Models\ScheduledBill;
use App\Services\PayCreditCardInvoice;
use App\Services\PayRecurringMonthlyBill;
use App\Services\PayScheduledBill;
use App\Support\FinancialBalance;
use App\Support\PaymentAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Payments extends Component
{
    public string $selectedMonth = '';
    public bool $paymentOpen = false;
    public string $selectedPayableKey = '';
    public string $paymentAccountId = '';
    public string $paymentAmount = 'R$ 0,00';
    public string $paidOn = '';

    public function mount(): void
    {
        $this->selectedMonth = now()->format('Y-m');
        $this->paidOn = now()->toDateString();
    }

    private function agenda(): array
    {
        $month = preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $this->selectedMonth)
            ? $this->selectedMonth
            : now()->format('Y-m');

        return app(PaymentAgenda::class)->forMonth((int) Auth::id(), $month);
    }

    public function goToMonth(string $month): void
    {
        if (preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $month)) {
            $this->selectedMonth = $month;
            $this->closePayment();
        }
    }

    public function updatedSelectedMonth(): void
    {
        $this->closePayment();
    }

    private function selectedEntry(): ?array
    {
        if ($this->selectedPayableKey === '') {
            return null;
        }

        return $this->agenda()['pending']->first(
            fn ($row) => $row['key'] === $this->selectedPayableKey
        );
    }

    public function openPayment(string $key): void
    {
        $row = $this->agenda()['pending']->first(fn ($item) => $item['key'] === $key);
        if ($row === null) {
            abort(404);
        }

        $this->resetValidation();
        $this->selectedPayableKey = $key;
        $this->paymentAmount = 'R$ '.FinancialBalance::formatted($row['amount_cents']);
        $this->paidOn = now()->toDateString();

        $accounts = $this->agenda()['accounts']->where('active', true);
        $enough = $accounts->first(fn ($account) => $account['usable_cents'] >= $row['amount_cents']);
        $fallback = $accounts->first();
        $this->paymentAccountId = (string) ($enough['id'] ?? $fallback['id'] ?? '');
        $this->paymentOpen = true;
    }

    public function closePayment(): void
    {
        $this->paymentOpen = false;
        $this->selectedPayableKey = '';
        $this->resetValidation();
    }

    private function moneyCents(string $text): int
    {
        if (! preg_match('/^R\$\s*(?:\d{1,3}(?:\.\d{3})+|\d+),\d{2}$/D', $text)) {
            throw ValidationException::withMessages([
                'paymentAmount' => 'Informe um valor como R$ 125,50.',
            ]);
        }

        $amount = (int) preg_replace('/\D/u', '', $text);
        if ($amount <= 0 || $amount > 99999999999) {
            throw ValidationException::withMessages([
                'paymentAmount' => 'O valor precisa ser maior que zero.',
            ]);
        }

        return $amount;
    }

    public function confirmPayment(): void
    {
        if (! $this->paymentOpen || $this->selectedPayableKey === '') {
            return;
        }

        $data = $this->validate([
            'paymentAccountId' => [
                'required', 'integer',
                Rule::exists('financial_accounts', 'id')
                    ->where('user_id', Auth::id())->where('active', true),
            ],
            'paymentAmount' => ['required', 'string'],
            'paidOn' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'paymentAccountId.required' => 'Selecione a conta pagadora.',
            'paidOn.before_or_equal' => 'Informe uma data de pagamento já realizada.',
        ]);

        $row = $this->selectedEntry();
        if ($row === null) {
            $this->addError('paymentAmount', 'Este compromisso não está mais pendente. Atualize a relação.');
            return;
        }

        $cents = $this->moneyCents($data['paymentAmount']);
        $userId = (int) Auth::id();
        $accountId = (int) $data['paymentAccountId'];

        if ($row['kind'] !== 'recurring' && $cents > $row['amount_cents']) {
            $this->addError('paymentAmount', 'O valor não pode ultrapassar o compromisso em aberto.');
            return;
        }

        if ($row['kind'] === 'bill' && $cents !== $row['amount_cents']) {
            $this->addError('paymentAmount', 'Este compromisso tem valor fixo. Corrija-o em Contas mensais antes de pagar.');
            return;
        }

        // A verificação definitiva de saldo e de duplicidade também ocorre
        // nos serviços, dentro de transações com bloqueio das contas.
        try {
            if ($row['kind'] === 'recurring') {
                app(PayRecurringMonthlyBill::class)->execute(
                    $userId, (int) $row['id'], (string) $row['period'],
                    $cents, $accountId, $data['paidOn']
                );
            } elseif ($row['kind'] === 'bill') {
                app(PayScheduledBill::class)->execute(
                    $userId, (int) $row['id'], $accountId, $data['paidOn']
                );
            } else {
                app(PayCreditCardInvoice::class)->execute(
                    $userId, (int) $row['id'], $accountId, $cents, $data['paidOn']
                );
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                $this->addError('paymentAmount', implode(' ', $messages));
            }
            return;
        }

        $this->closePayment();
        session()->flash('success', 'Pagamento registrado. O saldo da conta foi atualizado e a pendência recalculada.');
    }

    public function render()
    {
        $agenda = $this->agenda();
        $selectedRow = $this->paymentOpen ? $this->selectedEntry() : null;

        return view('livewire.payments', [
            'agenda' => $agenda,
            'selectedRow' => $selectedRow,
        ])->layout('layouts.app');
    }
}
