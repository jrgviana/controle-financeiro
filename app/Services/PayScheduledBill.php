<?php

namespace App\Services;

use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\ScheduledBill;
use App\Models\ScheduledBillPayment;
use App\Support\FinancialBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayScheduledBill
{
    /**
     * Permite pagamento integral ou parcial, sem perder o valor original
     * da cobrança. Cada parcela paga cria exatamente um gasto bancário.
     */
    public function execute(
        int $userId, int $billId, int $accountId, string $paidOn, ?int $cents = null
    ): DailyExpense {
        return DB::transaction(function () use ($userId, $billId, $accountId, $paidOn, $cents): DailyExpense {
            $account = FinancialAccount::query()
                ->where('user_id', $userId)->where('active', true)
                ->lockForUpdate()->findOrFail($accountId);

            $bill = ScheduledBill::query()
                ->where('user_id', $userId)->lockForUpdate()->findOrFail($billId);

            if ($bill->daily_expense_id !== null) {
                throw ValidationException::withMessages([
                    'billPaymentAccountId' => 'Esta conta já foi quitada.',
                ]);
            }

            $remaining = $bill->remainingCents();
            $amount = $cents ?? $remaining;
            if ($amount <= 0 || $amount > $remaining) {
                throw ValidationException::withMessages([
                    'billPaymentAccountId' => 'O valor ultrapassa o saldo em aberto desta conta.',
                ]);
            }

            $available = (new FinancialBalance())->currentCents($account);
            if ($available < $amount) {
                throw ValidationException::withMessages([
                    'billPaymentAccountId' => 'Saldo insuficiente. Disponível: R$ '.FinancialBalance::formatted(max(0, $available)),
                ]);
            }

            $expense = DailyExpense::create([
                'user_id' => $userId,
                'financial_account_id' => $accountId,
                'paid_on' => $paidOn,
                'description' => $bill->description,
                'category' => $bill->category,
                'amount' => number_format($amount / 100, 2, '.', ''),
                'notes' => trim('Pagamento de conta · Vencimento '.$bill->due_on->format('d/m/Y')
                    .($bill->notes ? ' · '.$bill->notes : '')),
            ]);

            ScheduledBillPayment::create([
                'user_id' => $userId,
                'scheduled_bill_id' => $bill->id,
                'daily_expense_id' => $expense->id,
            ]);

            if ($amount === $remaining) {
                $bill->update(['daily_expense_id' => $expense->id]);
            }

            return $expense;
        }, 3);
    }
}
