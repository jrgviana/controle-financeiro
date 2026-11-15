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
    ];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(DailyExpense::class, 'daily_expense_id');
    }
}
