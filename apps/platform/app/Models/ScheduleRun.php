<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'schedule_template_id',
        'status',
        'schedule_name_snapshot',
        'schedule_weekday_snapshot',
        'schedule_notes_snapshot',
        'started_at',
        'completed_at',
        'started_by_user_id',
        'completed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function scheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ScheduleRunBlock::class)->orderBy('position');
    }

    public function taskSessions(): HasMany
    {
        return $this->hasMany(TaskSession::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
