<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceAttentionCalibrationBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_attention_calibration_session_id',
        'sequence_number',
        'sample_count',
        'captured_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'sample_count' => 'integer',
            'captured_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DeviceAttentionCalibrationSession::class, 'device_attention_calibration_session_id');
    }
}
