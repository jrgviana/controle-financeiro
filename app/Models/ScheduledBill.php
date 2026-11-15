<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledBill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'description', 'category', 'due_on', 'amount', 'notes', 'daily_expense_id',
        'recurring_bill_id', 'period_month',
    ];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'period_month' => 'date', 'amount' => 'decimal:2'];
    }

    public function recurringBill(): BelongsTo
    {
        return $this->belongsTo(RecurringBill::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(DailyExpense::class, 'daily_expense_id');
    }
}
