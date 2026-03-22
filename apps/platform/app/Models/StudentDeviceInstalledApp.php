<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDeviceInstalledApp extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_device_id',
        'app_key',
        'display_name',
        'display_version',
        'publisher',
        'install_location',
        'first_seen_at',
        'last_seen_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function studentDevice(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class);
    }
}
