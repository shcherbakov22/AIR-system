<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Violation extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'rule_definition_id',
        'status',
        'rule_title_snapshot',
        'penalty_units',
        'occurred_at',
        'auto_generated_key',
        'notes',
        'reported_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'penalty_units' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function ruleDefinition(): BelongsTo
    {
        return $this->belongsTo(RuleDefinition::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(ViolationResolution::class);
    }

    public function pushUpSessions(): HasMany
    {
        return $this->hasMany(PushUpSession::class);
    }
}
