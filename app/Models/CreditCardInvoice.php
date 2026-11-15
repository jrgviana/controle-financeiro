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
