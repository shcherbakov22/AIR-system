<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EdgeClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_key',
        'client_type',
        'label',
        'version',
        'capabilities',
        'is_enabled',
        'last_seen_at',
        'last_seen_ip',
        'last_user_agent',
        'last_payload',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'is_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_payload' => 'array',
        ];
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(EdgeClientHeartbeat::class);
    }

    public function monitorCaptures(): HasMany
    {
        return $this->hasMany(StudentMonitorCapture::class);
    }
}
