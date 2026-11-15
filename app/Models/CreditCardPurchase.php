<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardPurchase extends Model
{
    protected $fillable = [
        'user_id', 'credit_card_id', 'purchased_on',
        'description', 'category', 'amount', 'installments_count', 'notes',
    ];

    protected function casts(): array
    {
        return ['purchased_on' => 'date', 'amount' => 'decimal:2', 'installments_count' => 'integer'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class, 'credit_card_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(CreditCardInstallment::class, 'credit_card_purchase_id');
    }
}
