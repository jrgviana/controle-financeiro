<?php

namespace App\Support;

use App\Models\CreditCard;
use Carbon\CarbonImmutable;

class CreditCardCycle
{
    private function dayInMonth(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->startOfMonth()->addDays(min($day, $month->daysInMonth) - 1);
    }

    /**
     * Compras até o fechamento entram no ciclo atual, inclusive no próprio dia.
     * Vencimento sempre ocorre após o fechamento (no mesmo mês ou no seguinte).
     */
    public function firstDue(CreditCard $card, string $purchaseDate): CarbonImmutable
    {
        $purchase = CarbonImmutable::parse($purchaseDate)->startOfDay();
        $month = $purchase->startOfMonth();
        $closing = $this->dayInMonth($month, $card->closing_day);

        if ($purchase->greaterThan($closing)) {
            $month = $month->addMonthNoOverflow();
            $closing = $this->dayInMonth($month, $card->closing_day);
        }

        $due = $this->dayInMonth($month, $card->due_day);

        if ($due->lessThanOrEqualTo($closing)) {
            $due = $this->dayInMonth($month->addMonthNoOverflow(), $card->due_day);
        }

        return $due;
    }

    public function dueForInstallment(CreditCard $card, CarbonImmutable $firstDue, int $index): CarbonImmutable
    {
        return $this->dayInMonth($firstDue->startOfMonth()->addMonthsNoOverflow($index), $card->due_day);
    }

    public function nextDue(CreditCard $card, string $dueOn): CarbonImmutable
    {
        return $this->dayInMonth(
            CarbonImmutable::parse($dueOn)->startOfMonth()->addMonthNoOverflow(),
            $card->due_day,
        );
    }

    public function nextBestPurchaseDate(CreditCard $card): CarbonImmutable
    {
        $today = CarbonImmutable::today();
        $month = $today->startOfMonth();
        $best = $this->dayInMonth($month, $card->closing_day)->addDay();

        if ($best->lessThan($today)) {
            $best = $this->dayInMonth($month->addMonthNoOverflow(), $card->closing_day)->addDay();
        }

        return $best;
    }
}
