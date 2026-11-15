<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardInstallmentAdvance extends Model
{
    protected $fillable = [
        'user_id', 'credit_card_installment_id', 'credit_card_invoice_payment_id',
    ];

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CreditCardInstallment::class, 'credit_card_installment_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CreditCardInvoicePayment::class, 'credit_card_invoice_payment_id');
    }
}
