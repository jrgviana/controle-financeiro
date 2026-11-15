<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringBill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'description', 'category', 'due_day', 'planned_amount',
        'starts_on', 'notes', 'active',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'due_day' => 'integer',
            'planned_amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ScheduledBill::class);
    }
}
