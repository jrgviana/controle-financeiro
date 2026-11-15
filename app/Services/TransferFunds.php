<?php

namespace App\Services;

use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Support\FinancialBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferFunds
{
    /**
     * A transferência interna movimenta dinheiro entre duas contas do usuário,
     * sem criar receita ou despesa. A validação de saldo ocorre dentro da transação.
     */
    public function execute(
        int $userId,
        int $fromId,
        int $toId,
        int $amountCents,
        string $date,
        ?string $notes = null,
        string $amountField = 'amount',
    ): AccountTransfer {
        if ($fromId === $toId || $amountCents <= 0 || $amountCents > 99999999999) {
            throw ValidationException::withMessages([
                $amountField => 'Confira o valor e selecione contas diferentes.',
            ]);
        }

        return DB::transaction(function () use ($userId, $fromId, $toId, $amountCents, $date, $notes, $amountField): AccountTransfer {
            // Sempre bloqueia na mesma ordem para evitar transferências concorrentes
            // consumirem duas vezes o saldo e reduzir risco de deadlocks.
            $accounts = FinancialAccount::query()
                ->where('user_id', $userId)
                ->where('active', true)
                ->whereIn('id', [$fromId, $toId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($accounts->count() !== 2) {
                throw ValidationException::withMessages([
                    $amountField => 'A conta de origem ou destino não está mais disponível.',
                ]);
            }

            $available = (new FinancialBalance())->currentCents($accounts->get($fromId));

            if ($available < $amountCents) {
                throw ValidationException::withMessages([
                    $amountField => 'Saldo insuficiente. Disponível na origem: R$ '
                        .FinancialBalance::formatted(max($available, 0)).'.',
                ]);
            }

            return AccountTransfer::create([
                'user_id' => $userId,
                'from_account_id' => $fromId,
                'to_account_id' => $toId,
                'transferred_on' => $date,
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'notes' => $notes,
            ]);
        }, 3);
    }
}
