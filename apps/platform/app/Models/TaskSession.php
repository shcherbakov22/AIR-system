<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'task_assignment_id',
        'schedule_run_id',
        'schedule_run_block_id',
        'task_template_id',
        'resumed_from_task_session_id',
        'status',
        'task_title_snapshot',
        'task_summary_snapshot',
        'task_instructions_snapshot',
        'assignment_notes_snapshot',
        'planned_duration_minutes',
        'started_at',
        'ended_at',
        'duration_seconds',
        'completion_notes',
        'started_by_user_id',
        'stopped_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'planned_duration_minutes' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function taskAssignment(): BelongsTo
    {
        return $this->belongsTo(TaskAssignment::class);
    }

    public function scheduleRun(): BelongsTo
    {
        return $this->belongsTo(ScheduleRun::class);
    }

    public function scheduleRunBlock(): BelongsTo
    {
        return $this->belongsTo(ScheduleRunBlock::class);
    }

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class);
    }

    public function resumedFromTaskSession(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resumed_from_task_session_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function stoppedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stopped_by_user_id');
    }

    public function aiOverseerDecisions(): HasMany
    {
        return $this->hasMany(AiOverseerDecision::class);
    }
}
