<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiOverseerDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'violation_id',
        'task_session_id',
        'schedule_run_id',
        'schedule_run_block_id',
        'mentor_chat_message_id',
        'request_type',
        'status',
        'decision',
        'confidence',
        'student_reason',
        'student_message',
        'mentor_summary',
        'reason',
        'action_taken',
        'context_snapshot',
        'raw_response',
        'model',
        'prompt_version',
        'decided_at',
        'mentor_notified_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'context_snapshot' => 'array',
            'raw_response' => 'array',
            'decided_at' => 'datetime',
            'mentor_notified_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    public function taskSession(): BelongsTo
    {
        return $this->belongsTo(TaskSession::class);
    }

    public function scheduleRun(): BelongsTo
    {
        return $this->belongsTo(ScheduleRun::class);
    }

    public function scheduleRunBlock(): BelongsTo
    {
        return $this->belongsTo(ScheduleRunBlock::class);
    }

    public function mentorChatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'mentor_chat_message_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiOverseerMessage::class)->orderBy('created_at')->orderBy('id');
    }
}
