<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\CreditCardInvoice;
use App\Models\CreditCardInvoicePayment;
use App\Models\FinancialAccount;
use App\Support\CreditCardCycle;
use App\Support\CreditCardLedger;
use App\Support\FinancialBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayCreditCardInvoice
{
    /**
     * Pagamento de fatura sem gerar DailyExpense (evita dupla saída do banco).
     * Retorna a próxima fatura após um pagamento parcial, se houver.
     */
    public function execute(int $userId, int $invoiceId, int $accountId, int $cents, string $paidOn): array
    {
        if ($cents <= 0) {
            throw ValidationException::withMessages(['cardPaymentAmount' => 'Informe um valor maior que zero.']);
        }

        return DB::transaction(function () use ($userId, $invoiceId, $accountId, $cents, $paidOn): array {
            $invoiceRef = CreditCardInvoice::query()
                ->where('user_id', $userId)->findOrFail($invoiceId);

            $card = CreditCard::query()
                ->where('user_id', $userId)->lockForUpdate()->findOrFail($invoiceRef->credit_card_id);
            $invoice = CreditCardInvoice::query()
                ->where('user_id', $userId)->where('credit_card_id', $card->id)
                ->lockForUpdate()->findOrFail($invoiceId);

            $account = FinancialAccount::query()
                ->where('user_id', $userId)->where('active', true)
                ->lockForUpdate()->findOrFail($accountId);

            $laterPaid = CreditCardInvoicePayment::query()
                ->where('user_id', $userId)
                ->whereHas('invoice', fn ($q) => $q->where('credit_card_id', $card->id)
                    ->whereDate('due_on', '>', $invoice->due_on->toDateString()))
                ->exists();
            if ($laterPaid) {
                throw ValidationException::withMessages([
                    'cardPaymentAmount' => 'Já existe pagamento de fatura posterior. Registre o pagamento na fatura mais recente.',
                ]);
            }

            $row = app(CreditCardLedger::class)->invoices($card)
                ->first(fn ($entry) => $entry['invoice']->id === $invoice->id);

            if ($row === null || $cents > $row['remaining_cents']) {
                throw ValidationException::withMessages([
                    'cardPaymentAmount' => 'O valor informado ultrapassa o saldo em aberto da fatura.',
                ]);
            }

            $available = (new FinancialBalance())->currentCents($account);
            if ($available < $cents) {
                throw ValidationException::withMessages([
                    'cardPaymentAmount' => 'Saldo insuficiente na conta pagadora. Disponível: R$ '
                        .FinancialBalance::formatted(max(0, $available)),
                ]);
            }

            CreditCardInvoicePayment::create([
                'user_id' => $userId,
                'credit_card_invoice_id' => $invoice->id,
                'financial_account_id' => $accountId,
                'paid_on' => $paidOn,
                'amount' => number_format($cents / 100, 2, '.', ''),
            ]);

            $remaining = $row['remaining_cents'] - $cents;
            if ($remaining > 0) {
                $nextDue = app(CreditCardCycle::class)
                    ->nextDue($card, $invoice->due_on->toDateString());
                CreditCardInvoice::forDue($card, $nextDue->toDateString());
            }

            return ['remaining_cents' => $remaining, 'card_id' => $card->id];
        }, 3);
    }
}
