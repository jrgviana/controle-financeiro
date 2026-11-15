<?php

namespace App\Services;

use App\Models\DailyExpense;
use App\Models\FinancialAccount;
use App\Models\ScheduledBill;
use App\Support\FinancialBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayScheduledBill
{
    public function execute(int $userId, int $billId, int $accountId, string $paidOn): DailyExpense
    {
        return DB::transaction(function () use ($userId, $billId, $accountId, $paidOn): DailyExpense {
            $account = FinancialAccount::query()
                ->where('user_id', $userId)->where('active', true)
                ->lockForUpdate()->findOrFail($accountId);

            $bill = ScheduledBill::query()
                ->where('user_id', $userId)->lockForUpdate()->findOrFail($billId);

            if ($bill->daily_expense_id !== null) {
                throw ValidationException::withMessages([
                    'billPaymentAccountId' => 'Esta conta a vencer já foi quitada.',
                ]);
            }

            $amountCents = FinancialBalance::cents($bill->amount);
            $availableCents = (new FinancialBalance())->currentCents($account);

            if ($availableCents < $amountCents) {
                throw ValidationException::withMessages([
                    'billPaymentAccountId' => 'Saldo insuficiente. Disponível: R$ '.FinancialBalance::formatted(max(0, $availableCents)),
                ]);
            }

            $expense = DailyExpense::create([
                'user_id' => $userId,
                'financial_account_id' => $accountId,
                'paid_on' => $paidOn,
                'description' => $bill->description,
                'category' => $bill->category,
                'amount' => $bill->amount,
                'notes' => trim('Conta programada · Vencimento '.$bill->due_on->format('d/m/Y')
                    .($bill->notes ? ' · '.$bill->notes : '')),
            ]);

            $bill->update(['daily_expense_id' => $expense->id]);

            return $expense;
        }, 3);
    }
}
