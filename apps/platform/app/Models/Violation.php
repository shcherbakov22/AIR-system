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

    public function elapsedOpenMinutes(): int
    {
        if ($this->status !== 'open' || ! $this->occurred_at) {
            return 0;
        }

        return intdiv(max(0, $this->occurred_at->diffInSeconds(now())), 60);
    }

    public function effectivePenaltyUnits(): int
    {
        return max(0, (int) $this->penalty_units) + $this->elapsedOpenMinutes();
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

    public function aiOverseerDecisions(): HasMany
    {
        return $this->hasMany(AiOverseerDecision::class);
    }
}
