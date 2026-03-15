<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_device_id',
        'requested_by_user_id',
        'command_type',
        'status',
        'payload',
        'requested_at',
        'leased_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'requested_at' => 'datetime',
            'leased_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function studentDevice(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(DeviceCommandResult::class);
    }
}
