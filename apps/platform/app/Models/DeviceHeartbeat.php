<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceHeartbeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_device_id',
        'received_at',
        'ip_address',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function studentDevice(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class);
    }
}
