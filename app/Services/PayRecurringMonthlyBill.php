<?php

namespace App\Services;

use App\Models\RecurringBill;
use App\Models\ScheduledBill;
use App\Support\FinancialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayRecurringMonthlyBill
{
    /**
     * Confirma o valor real de uma competência e paga exatamente uma vez.
     * A criação/ajuste da ocorrência e o débito são atômicos: se a conta
     * bancária não tiver saldo, nenhuma cobrança é materializada.
     */
    public function execute(
        int $userId,
        int $recurringId,
        string $periodMonth,
        int $amountCents,
        int $accountId,
        string $paidOn
    ): void {
        if (! preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $periodMonth)
            || $amountCents <= 0 || $amountCents > 99999999999) {
            throw ValidationException::withMessages([
                'paymentAmount' => 'Informe a competência e um valor de pagamento válidos.',
            ]);
        }

        DB::transaction(function () use ($userId, $recurringId, $periodMonth, $amountCents, $accountId, $paidOn): void {
            $fixed = RecurringBill::query()->where('user_id', $userId)
                ->lockForUpdate()->findOrFail($recurringId);
            $month = CarbonImmutable::parse($periodMonth.'-01')->startOfMonth();

            $bill = ScheduledBill::query()->where('user_id', $userId)
                ->where('recurring_bill_id', $fixed->id)
                ->whereDate('period_month', $month->toDateString())
                ->lockForUpdate()->first();

            if ($bill?->daily_expense_id !== null) {
                throw ValidationException::withMessages(['paymentAmount' => 'Essa conta já foi paga.']);
            }

            if ($bill === null && (! $fixed->active || $month->lessThan($fixed->starts_on->startOfMonth()))) {
                throw ValidationException::withMessages([
                    'paymentAmount' => 'Essa competência não está ativa para pagamento.',
                ]);
            }

            $values = [
                'description' => $bill?->description ?? $fixed->description,
                'category' => $bill?->category ?? $fixed->category,
                'due_on' => $bill?->due_on?->toDateString()
                    ?? $month->addDays(min($fixed->due_day, $month->daysInMonth) - 1)->toDateString(),
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'notes' => $bill?->notes ?? $fixed->notes,
            ];

            if ($bill === null) {
                $bill = ScheduledBill::create([
                    'user_id' => $userId,
                    'recurring_bill_id' => $fixed->id,
                    'period_month' => $month->toDateString(),
                    ...$values,
                ]);
            } else {
                $bill->update($values);
            }

            app(PayScheduledBill::class)->execute($userId, $bill->id, $accountId, $paidOn);
        }, 3);
    }
}
