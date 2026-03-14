<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeechAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'violation_id',
        'task_session_id',
        'kind',
        'message',
        'meta',
        'spoken_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'spoken_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    public function taskSession(): BelongsTo
    {
        return $this->belongsTo(TaskSession::class);
    }
}
