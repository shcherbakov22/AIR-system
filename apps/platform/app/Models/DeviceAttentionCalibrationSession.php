<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceAttentionCalibrationSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_device_id',
        'session_uuid',
        'provider',
        'status',
        'sample_count',
        'started_at',
        'completed_at',
        'training_requested_at',
        'model_ready_at',
        'model_version',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'sample_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'training_requested_at' => 'datetime',
            'model_ready_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class, 'student_device_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DeviceAttentionCalibrationBatch::class, 'device_attention_calibration_session_id');
    }
}
