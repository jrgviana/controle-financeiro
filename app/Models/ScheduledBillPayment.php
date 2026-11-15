<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledBillPayment extends Model
{
    protected $fillable = ['user_id', 'scheduled_bill_id', 'daily_expense_id'];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(ScheduledBill::class, 'scheduled_bill_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(DailyExpense::class, 'daily_expense_id');
    }
}
