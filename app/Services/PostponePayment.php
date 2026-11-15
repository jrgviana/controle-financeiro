<?php

namespace App\Services;

use App\Models\PaymentPostponement;
use App\Support\PaymentAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostponePayment
{
    /**
     * Registra a decisão de não pagar neste mês. O vencimento original
     * nunca é alterado. A pendência aparece de novo no mês seguinte.
     */
    public function execute(
        int $userId,
        string $payableKey,
        string $month,
        string $source = 'manual',
        ?string $reason = null
    ): PaymentPostponement {
        if (! preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D', $month)
            || ! in_array($source, ['manual', 'partial'], true)) {
            throw ValidationException::withMessages(['postpone' => 'Mês ou operação inválidos.']);
        }

        return DB::transaction(function () use ($userId, $payableKey, $month, $source, $reason): PaymentPostponement {
            $row = app(PaymentAgenda::class)->forMonth($userId, $month)['pending']
                ->first(fn ($item) => $item['key'] === $payableKey);
            if ($row === null) {
                throw ValidationException::withMessages([
                    'postpone' => 'O compromisso não está mais pendente neste mês.',
                ]);
            }

            $from = CarbonImmutable::parse($month.'-01');
            $to = $from->addMonth();

            // A restrição única protege de cliques repetidos; uma nova decisão
            // no mês seguinte constitui um novo registro de histórico.
            return PaymentPostponement::query()->firstOrCreate([
                'user_id' => $userId,
                'payable_key' => $payableKey,
                'from_month' => $from->toDateString(),
            ], [
                'to_month' => $to->toDateString(),
                'description' => $row['description'],
                'remaining_amount' => number_format($row['amount_cents'] / 100, 2, '.', ''),
                'source' => $source,
                'reason' => trim($reason ?? '') ?: null,
            ]);
        }, 3);
    }
}
