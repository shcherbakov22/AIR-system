<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use App\Models\Violation;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'status',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ScheduleTemplate::class);
    }

    public function scheduleRuns(): HasMany
    {
        return $this->hasMany(ScheduleRun::class);
    }

    public function taskSessions(): HasMany
    {
        return $this->hasMany(TaskSession::class);
    }

    public function ruleDefinitions(): HasMany
    {
        return $this->hasMany(RuleDefinition::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }

    public function hasOpenViolations(): bool
    {
        return $this->violations()->where('status', 'open')->exists();
    }

    public function setting(): HasOne
    {
        return $this->hasOne(StudentSetting::class);
    }

    public function consequenceProfile(): HasOne
    {
        return $this->hasOne(StudentConsequenceProfile::class);
    }

    public function canManageOwnSchedule(): bool
    {
        return $this->setting?->can_manage_own_schedule ?? true;
    }

    public function canUseAdHocTimer(): bool
    {
        return $this->setting?->can_use_ad_hoc_timer ?? true;
    }
}
