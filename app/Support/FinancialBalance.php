<?php

namespace App\Support;

use App\Models\FinancialAccount;
use InvalidArgumentException;

class FinancialBalance
{
    /**
     * Converte um decimal de banco de dados (ex.: "12.50") em centavos
     * sem depender de arredondamentos em ponto flutuante.
     */
    public static function cents(string|int|float|null $value): int
    {
        $value = (string) ($value ?? '0');

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Valor decimal financeiro inválido.');
        }

        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    public static function formatted(int $cents): string
    {
        return number_format(abs($cents) / 100, 2, ',', '.');
    }

    public static function loadedCents(FinancialAccount $account): int
    {
        return self::cents($account->initial_balance)
            + self::cents($account->income_received ?? 0)
            + self::cents($account->transfers_received ?? 0)
            - self::cents($account->expenses_paid ?? 0)
            - self::cents($account->transfers_sent ?? 0)
            - self::cents($account->card_payments ?? 0);
    }

    /**
     * Calcula o saldo registrado neste instante. Usar após bloquear
     * as contas com lockForUpdate() na transação de transferência.
     */
    public function currentCents(FinancialAccount $account): int
    {
        return self::cents($account->initial_balance)
            + self::cents($account->receivedIncomes()->sum('amount'))
            + self::cents($account->incomingTransfers()->sum('amount'))
            - self::cents($account->dailyExpenses()->sum('amount'))
            - self::cents($account->outgoingTransfers()->sum('amount'))
            - self::cents($account->creditCardPayments()->sum('amount'));
    }
}
