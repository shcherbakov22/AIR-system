<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenaltyAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PenaltyTransaction::class);
    }

    public function currentBalanceUnits(): int
    {
        if ($this->relationLoaded('transactions')) {
            return (int) $this->transactions->sum('delta_units');
        }

        return (int) $this->transactions()->sum('delta_units');
    }
}
