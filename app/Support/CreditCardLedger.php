<?php

namespace App\Support;

use App\Models\CreditCard;
use Illuminate\Support\Collection;

class CreditCardLedger
{
    /**
     * Cada fatura contém suas parcelas e juros manuais. O saldo não quitado
     * é projetado para a fatura seguinte, sem criar uma segunda dívida.
     *
     * Atenção: o saldo anterior das faturas futuras é projeção e muda quando
     * pagamentos de meses anteriores são registrados.
     */
    public function invoices(CreditCard $card): Collection
    {
        $invoices = $card->invoices()
            ->with([
                'installments.purchase',
                'charges',
                'payments.financialAccount',
            ])
            ->orderBy('due_on')
            ->get();

        $carry = 0;

        return $invoices->map(function ($invoice) use (&$carry): array {
            $installmentsCents = $invoice->installments->sum(fn ($item) => FinancialBalance::cents($item->amount));
            $interestCents = $invoice->charges->sum(fn ($item) => FinancialBalance::cents($item->amount));
            $paidCents = $invoice->payments->sum(fn ($item) => FinancialBalance::cents($item->amount));
            $openingCents = $carry;
            $totalCents = $openingCents + $installmentsCents + $interestCents;
            $carry = max(0, $totalCents - $paidCents);

            return [
                'invoice' => $invoice,
                'installments_cents' => $installmentsCents,
                'interest_cents' => $interestCents,
                'carry_cents' => $openingCents,
                'total_cents' => $totalCents,
                'paid_cents' => $paidCents,
                'remaining_cents' => $carry,
            ];
        });
    }

    public function totalDebt(CreditCard $card): int
    {
        return (int) ($this->invoices($card)->last()['remaining_cents'] ?? 0);
    }
}
