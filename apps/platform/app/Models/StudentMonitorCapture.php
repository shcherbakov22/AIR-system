<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMonitorCapture extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'edge_client_id',
        'student_device_id',
        'task_session_id',
        'schedule_run_id',
        'capture_kind',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'captured_at',
        'uploaded_at',
        'task_title_snapshot',
        'app_name_snapshot',
        'window_title_snapshot',
        'browser_domain_snapshot',
        'source_label',
        'source_version',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'captured_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function edgeClient(): BelongsTo
    {
        return $this->belongsTo(EdgeClient::class);
    }

    public function studentDevice(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class);
    }

    public function taskSession(): BelongsTo
    {
        return $this->belongsTo(TaskSession::class);
    }

    public function scheduleRun(): BelongsTo
    {
        return $this->belongsTo(ScheduleRun::class);
    }
}
