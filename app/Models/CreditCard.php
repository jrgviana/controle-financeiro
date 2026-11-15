<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCard extends Model
{
    protected $fillable = ['user_id', 'name', 'issuer', 'brand', 'credit_limit', 'closing_day', 'due_day', 'active'];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'closing_day' => 'integer',
            'due_day' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CreditCardPurchase::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CreditCardInvoice::class);
    }
}
