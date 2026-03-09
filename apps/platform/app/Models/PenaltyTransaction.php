<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenaltyTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'penalty_account_id',
        'violation_id',
        'type',
        'delta_units',
        'notes',
        'recorded_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'delta_units' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function penaltyAccount(): BelongsTo
    {
        return $this->belongsTo(PenaltyAccount::class);
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
