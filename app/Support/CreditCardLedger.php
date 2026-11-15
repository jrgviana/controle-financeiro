<?php

namespace App\Support;

use App\Models\CreditCard;
use Illuminate\Support\Collection;

class CreditCardLedger
{
    /**
     * Faturas futuras mostram apenas as parcelas de seu próprio vencimento.
     * Saldo anterior só é transportado quando há pagamento parcial ou
     * quando a fatura anterior venceu sem ter sido integralmente quitada.
     */
    public function invoices(CreditCard $card): Collection
    {
        $invoices = $card->invoices()
            ->with(['installments.purchase', 'charges', 'payments.financialAccount'])
            ->orderBy('due_on')
            ->get();

        $carry = 0;

        return $invoices->map(function ($invoice) use (&$carry): array {
            $installments = $invoice->installments->sum(fn ($x) => FinancialBalance::cents($x->amount));
            $charges = $invoice->charges->sum(fn ($x) => FinancialBalance::cents($x->amount));
            $paid = $invoice->payments->sum(fn ($x) => FinancialBalance::cents($x->amount));
            $opening = $carry;
            $total = $opening + $installments + $charges;
            $remaining = max(0, $total - $paid);

            // Não acumular faturas FUTURAS não vencidas como se estivessem em atraso.
            $carry = ($paid > 0 || $invoice->due_on->isPast()) ? $remaining : 0;

            return [
                'invoice' => $invoice,
                'installments_cents' => $installments,
                'interest_cents' => $charges,
                'carry_cents' => $opening,
                'total_cents' => $total,
                'paid_cents' => $paid,
                'remaining_cents' => $remaining,
            ];
        });
    }

    /**
     * Total efetivamente comprometido, sem somar novamente saldo transportado
     * entre faturas. Inclui todas as parcelas futuras e juros lançados.
     */
    public function totalDebt(CreditCard $card): int
    {
        $invoices = $this->invoices($card);

        return max(0, $invoices->sum(
            fn ($row) => $row['installments_cents'] + $row['interest_cents'] - $row['paid_cents']
        ));
    }
}
