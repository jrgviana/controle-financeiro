<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardInvoice extends Model
{
    protected $fillable = ['user_id', 'credit_card_id', 'due_on'];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    /**
     * Consulta por data de calendário (não igualdade de string). Isso evita
     * duplicidade quando SQLite armazena DATE como "YYYY-MM-DD 00:00:00".
     * O chamador deve bloquear o cartão na transação antes de criar faturas.
     */
    public static function forDue(CreditCard $card, string $date): self
    {
        return static::query()
            ->where('credit_card_id', $card->id)
            ->whereDate('due_on', $date)
            ->first()
            ?? static::create([
                'user_id' => $card->user_id,
                'credit_card_id' => $card->id,
                'due_on' => $date,
            ]);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class, 'credit_card_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(CreditCardInstallment::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(CreditCardInvoiceCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditCardInvoicePayment::class);
    }
}
