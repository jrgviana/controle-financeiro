<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPostponement extends Model
{
    protected $fillable = [
        'user_id', 'payable_key', 'from_month', 'to_month',
        'description', 'remaining_amount', 'reason', 'source',
    ];

    protected function casts(): array
    {
        return [
            'from_month' => 'date',
            'to_month' => 'date',
            'remaining_amount' => 'decimal:2',
        ];
    }
}
