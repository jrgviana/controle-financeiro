<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'institution',
        'initial_balance',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function dailyExpenses(): HasMany
    {
        return $this->hasMany(DailyExpense::class);
    }

    public function receivedIncomes(): HasMany
    {
        return $this->hasMany(ReceivedIncome::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
