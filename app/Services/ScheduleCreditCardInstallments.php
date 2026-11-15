<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\CreditCardInstallment;
use App\Models\CreditCardInstallmentPlan;
use App\Models\CreditCardInvoice;
use App\Support\CreditCardLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleCreditCardInstallments
{
    /**
     * Muda parcelas INTEGRALMENTE futuras para a fatura do mês escolhido.
     * Não cria pagamentos bancários; somente reprograma vencimentos.
     * Usa o vencimento real do cartão, preservando número original da parcela.
     *
     * @param array<int, int|string> $installmentIds
     * @return array{count:int,target_invoice_id:int,target_month:string}
     */
    public function execute(int $userId, int $sourceInvoiceId, array $installmentIds, string $targetMonth): array
    {
        $ids = array_map('intval', $installmentIds);
        if ($ids === [] || count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'advanceInstallmentIds' => 'Selecione uma ou mais parcelas distintas.',
            ]);
        }

        if (! preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $targetMonth)) {
            throw ValidationException::withMessages(['advanceTargetMonth' => 'Informe um mês válido.']);
        }

        return DB::transaction(function () use ($userId, $sourceInvoiceId, $ids, $targetMonth): array {
            $sourceRef = CreditCardInvoice::query()->where('user_id', $userId)->findOrFail($sourceInvoiceId);
            $card = CreditCard::query()->where('user_id', $userId)
                ->lockForUpdate()->findOrFail($sourceRef->credit_card_id);
            $source = CreditCardInvoice::query()->where('user_id', $userId)
                ->where('credit_card_id', $card->id)
                ->lockForUpdate()->findOrFail($sourceInvoiceId);

            $month = CarbonImmutable::parse($targetMonth.'-01')->startOfMonth();
            if ($month->lessThan(CarbonImmutable::today()->startOfMonth())) {
                throw ValidationException::withMessages(['advanceTargetMonth' => 'Não é possível antecipar para um mês anterior ao atual.']);
            }

            $targetDue = $month->addDays(min((int) $card->due_day, $month->daysInMonth) - 1);
            if (! $targetDue->lessThan($source->due_on)) {
                throw ValidationException::withMessages(['advanceTargetMonth' => 'Escolha um mês anterior ao vencimento original.']);
            }

            if ($source->payments()->exists()) {
                throw ValidationException::withMessages(['advanceInstallmentIds' => 'Esta fatura já possui pagamentos; não é possível reagendar parcelas dela.']);
            }

            $installments = CreditCardInstallment::query()
                ->where('user_id', $userId)
                ->where('credit_card_invoice_id', $source->id)
                ->whereIn('id', $ids)
                ->with(['advance', 'scheduledPlan'])
                ->orderBy('id')->lockForUpdate()->get();

            if ($installments->count() !== count($ids)) {
                throw ValidationException::withMessages(['advanceInstallmentIds' => 'Uma ou mais parcelas não pertencem à fatura selecionada.']);
            }

            if ($installments->contains(fn ($row) => $row->advance !== null || $row->scheduledPlan !== null)) {
                throw ValidationException::withMessages(['advanceInstallmentIds' => 'Alguma parcela já foi antecipada ou paga.']);
            }

            $target = CreditCardInvoice::forDue($card, $targetDue->toDateString());
            if ($target->payments()->exists()) {
                throw ValidationException::withMessages(['advanceTargetMonth' => 'A fatura de destino já recebeu pagamento. Escolha outro mês.']);
            }

            foreach ($installments as $installment) {
                CreditCardInstallmentPlan::create([
                    'user_id' => $userId,
                    'credit_card_installment_id' => $installment->id,
                    'original_invoice_id' => $source->id,
                    'target_invoice_id' => $target->id,
                ]);
                $installment->update(['credit_card_invoice_id' => $target->id]);
            }

            return [
                'count' => $installments->count(),
                'target_invoice_id' => $target->id,
                'target_month' => $targetMonth,
            ];
        }, 3);
    }

    public function undo(int $userId, int $installmentId): void
    {
        DB::transaction(function () use ($userId, $installmentId): void {
            $planRef = CreditCardInstallmentPlan::query()
                ->where('user_id', $userId)
                ->where('credit_card_installment_id', $installmentId)
                ->firstOrFail();
            $cardId = $planRef->targetInvoice->credit_card_id;
            $card = CreditCard::query()->where('user_id', $userId)
                ->lockForUpdate()->findOrFail($cardId);

            $plan = CreditCardInstallmentPlan::query()
                ->where('user_id', $userId)
                ->where('credit_card_installment_id', $installmentId)
                ->lockForUpdate()->firstOrFail();

            $target = CreditCardInvoice::query()->where('user_id', $userId)
                ->where('credit_card_id', $card->id)
                ->lockForUpdate()->findOrFail($plan->target_invoice_id);

            if ($target->payments()->exists()) {
                throw ValidationException::withMessages([
                    'advanceInstallmentIds' => 'Não é possível desfazer: a fatura de destino já recebeu pagamento.',
                ]);
            }

            $installment = CreditCardInstallment::query()
                ->where('user_id', $userId)->where('credit_card_invoice_id', $target->id)
                ->lockForUpdate()->findOrFail($installmentId);

            $installment->update(['credit_card_invoice_id' => $plan->original_invoice_id]);
            $plan->delete();
        }, 3);
    }
}
