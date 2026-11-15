<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\CreditCardInstallment;
use App\Models\CreditCardInstallmentAdvance;
use App\Models\CreditCardInvoice;
use App\Models\CreditCardInvoicePayment;
use App\Models\FinancialAccount;
use App\Support\CreditCardLedger;
use App\Support\FinancialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdvanceCreditCardInstallments
{
    /**
     * Antecipação de parcelas inteiras pertencentes à mesma fatura futura.
     * Cria pagamento bancário uma única vez e vincula cada parcela a esse
     * pagamento para impedir antecipação em duplicidade.
     *
     * @param array<int, int> $installmentIds
     * @return array{count:int, amount_cents:int}
     */
    public function execute(
        int $userId,
        int $invoiceId,
        array $installmentIds,
        int $accountId,
        string $paidOn,
    ): array {
        $ids = array_values(array_unique(array_map('intval', $installmentIds)));
        if (count($ids) === 0 || count($ids) !== count($installmentIds)) {
            throw ValidationException::withMessages([
                'advanceInstallmentIds' => 'Escolha uma ou mais parcelas distintas para antecipar.',
            ]);
        }

        return DB::transaction(function () use ($userId, $invoiceId, $ids, $accountId, $paidOn): array {
            $invoiceRef = CreditCardInvoice::query()
                ->where('user_id', $userId)
                ->findOrFail($invoiceId);

            $card = CreditCard::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->findOrFail($invoiceRef->credit_card_id);

            $invoice = CreditCardInvoice::query()
                ->where('user_id', $userId)
                ->where('credit_card_id', $card->id)
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if (! $invoice->due_on->startOfDay()->greaterThan(CarbonImmutable::today())) {
                throw ValidationException::withMessages([
                    'advanceInstallmentIds' => 'A antecipação individual só é permitida em faturas com vencimento futuro.',
                ]);
            }

            $installments = CreditCardInstallment::query()
                ->where('user_id', $userId)
                ->where('credit_card_invoice_id', $invoice->id)
                ->whereIn('id', $ids)
                ->with('advance')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($installments->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'advanceInstallmentIds' => 'Uma das parcelas não pertence à fatura selecionada.',
                ]);
            }

            if ($installments->contains(fn ($row) => $row->advance !== null)) {
                throw ValidationException::withMessages([
                    'advanceInstallmentIds' => 'Uma parcela escolhida já foi antecipada.',
                ]);
            }

            $amountCents = $installments->sum(fn ($row) => FinancialBalance::cents($row->amount));
            $ledgerRow = app(CreditCardLedger::class)->invoices($card)
                ->first(fn ($row) => $row['invoice']->id === $invoice->id);

            if ($ledgerRow === null || $amountCents > $ledgerRow['remaining_cents']) {
                throw ValidationException::withMessages([
                    'advanceInstallmentIds' => 'As parcelas selecionadas ultrapassam o saldo em aberto da fatura.',
                ]);
            }

            $account = FinancialAccount::query()
                ->where('user_id', $userId)
                ->where('active', true)
                ->lockForUpdate()
                ->findOrFail($accountId);

            if ((new FinancialBalance())->currentCents($account) < $amountCents) {
                throw ValidationException::withMessages([
                    'advanceAccountId' => 'Saldo insuficiente na conta escolhida para antecipar estas parcelas.',
                ]);
            }

            $payment = CreditCardInvoicePayment::create([
                'user_id' => $userId,
                'credit_card_invoice_id' => $invoice->id,
                'financial_account_id' => $account->id,
                'paid_on' => $paidOn,
                'amount' => number_format($amountCents / 100, 2, '.', ''),
            ]);

            foreach ($installments as $installment) {
                CreditCardInstallmentAdvance::create([
                    'user_id' => $userId,
                    'credit_card_installment_id' => $installment->id,
                    'credit_card_invoice_payment_id' => $payment->id,
                ]);
            }

            return ['count' => $installments->count(), 'amount_cents' => $amountCents];
        }, 3);
    }
}
