<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_template_id',
        'task_template_id',
        'task_title',
        'task_summary',
        'task_instructions',
        'position',
        'start_time',
        'duration_minutes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    public function scheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class);
    }

    public function resolvedTaskTitle(): string
    {
        return $this->task_title
            ?? $this->taskTemplate?->title
            ?? 'Задание без названия';
    }

    public function resolvedTaskSummary(): ?string
    {
        return $this->task_summary
            ?? $this->taskTemplate?->summary;
    }

    public function resolvedTaskInstructions(): ?string
    {
        return $this->task_instructions
            ?? $this->taskTemplate?->instructions;
    }

    public function runBlocks(): HasMany
    {
        return $this->hasMany(ScheduleRunBlock::class);
    }
}
