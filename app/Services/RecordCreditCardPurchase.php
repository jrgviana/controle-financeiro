<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\CreditCardInstallment;
use App\Models\CreditCardInvoice;
use App\Models\CreditCardPurchase;
use App\Support\CreditCardCycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordCreditCardPurchase
{
    /**
     * Registra uma compra uma única vez e distribui todas as parcelas
     * nas faturas corretas, sem movimentar o saldo das contas bancárias.
     */
    public function execute(
        int $userId,
        int $cardId,
        string $purchasedOn,
        string $description,
        string $category,
        int $amountCents,
        int $installmentsCount,
        ?string $notes = null,
    ): CreditCardPurchase {
        if ($amountCents <= 0 || $amountCents > 99999999999 || $installmentsCount < 1
            || $installmentsCount > 60 || $installmentsCount > $amountCents) {
            throw ValidationException::withMessages([
                'amount' => 'Confira o valor e as parcelas: cada parcela precisa ter pelo menos R$ 0,01.',
            ]);
        }

        return DB::transaction(function () use (
            $userId, $cardId, $purchasedOn, $description, $category,
            $amountCents, $installmentsCount, $notes
        ): CreditCardPurchase {
            $card = CreditCard::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->findOrFail($cardId);

            if (! $card->active) {
                throw ValidationException::withMessages([
                    'cardId' => 'Este cartão está desativado e não aceita novas compras.',
                ]);
            }

            $cycle = app(CreditCardCycle::class);
            $firstDue = $cycle->firstDue($card, $purchasedOn);

            $purchase = CreditCardPurchase::create([
                'user_id' => $userId,
                'credit_card_id' => $cardId,
                'purchased_on' => $purchasedOn,
                'description' => trim($description),
                'category' => $category,
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'installments_count' => $installmentsCount,
                'notes' => trim($notes ?? '') ?: null,
            ]);

            $baseCents = intdiv($amountCents, $installmentsCount);
            $remainder = $amountCents % $installmentsCount;

            for ($index = 0; $index < $installmentsCount; $index++) {
                $due = $cycle->dueForInstallment($card, $firstDue, $index);
                $invoice = CreditCardInvoice::forDue($card, $due->toDateString());

                CreditCardInstallment::create([
                    'user_id' => $userId,
                    'credit_card_purchase_id' => $purchase->id,
                    'credit_card_invoice_id' => $invoice->id,
                    'installment_number' => $index + 1,
                    'amount' => number_format(
                        ($baseCents + ($index === $installmentsCount - 1 ? $remainder : 0)) / 100,
                        2, '.', ''
                    ),
                ]);
            }

            return $purchase;
        }, 3);
    }
}
