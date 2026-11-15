<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardInstallment extends Model
{
    protected $fillable = [
        'user_id', 'credit_card_purchase_id', 'credit_card_invoice_id', 'installment_number', 'amount',
    ];

    protected function casts(): array
    {
        return ['installment_number' => 'integer', 'amount' => 'decimal:2'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(CreditCardPurchase::class, 'credit_card_purchase_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CreditCardInvoice::class, 'credit_card_invoice_id');
    }
}
