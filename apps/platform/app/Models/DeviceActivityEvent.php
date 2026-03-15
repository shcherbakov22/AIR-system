<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceActivityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_device_id',
        'event_type',
        'app_name',
        'window_title',
        'browser_domain',
        'payload',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'observed_at' => 'datetime',
        ];
    }

    public function studentDevice(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class);
    }
}
