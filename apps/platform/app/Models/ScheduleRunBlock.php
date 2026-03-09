<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleRunBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_run_id',
        'schedule_entry_id',
        'task_template_id',
        'position',
        'status',
        'start_time_snapshot',
        'duration_minutes_snapshot',
        'task_title_snapshot',
        'task_summary_snapshot',
        'task_instructions_snapshot',
        'entry_notes_snapshot',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'duration_minutes_snapshot' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function scheduleRun(): BelongsTo
    {
        return $this->belongsTo(ScheduleRun::class);
    }

    public function scheduleEntry(): BelongsTo
    {
        return $this->belongsTo(ScheduleEntry::class);
    }

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class);
    }

    public function taskSessions(): HasMany
    {
        return $this->hasMany(TaskSession::class);
    }
}
