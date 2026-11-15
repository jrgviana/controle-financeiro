<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardInstallmentPlan extends Model
{
    protected $fillable = [
        'user_id', 'credit_card_installment_id', 'original_invoice_id', 'target_invoice_id',
    ];

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CreditCardInstallment::class, 'credit_card_installment_id');
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(CreditCardInvoice::class, 'original_invoice_id');
    }

    public function targetInvoice(): BelongsTo
    {
        return $this->belongsTo(CreditCardInvoice::class, 'target_invoice_id');
    }
}
