<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'summary',
        'instructions',
        'default_duration_minutes',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'default_duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function scheduleEntries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    public function taskSessions(): HasMany
    {
        return $this->hasMany(TaskSession::class);
    }
}
