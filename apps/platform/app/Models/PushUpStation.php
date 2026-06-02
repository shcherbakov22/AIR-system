<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PushUpStation extends Model
{
    use HasFactory;

    protected $fillable = [
        'station_key',
        'name',
        'firmware_version',
        'ip_address',
        'state',
        'sensor_status',
        'free_heap',
        'distance',
        'debug_payload',
        'connected_by_user_id',
        'last_seen_at',
        'last_claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_claimed_at' => 'datetime',
            'free_heap' => 'integer',
            'distance' => 'integer',
            'debug_payload' => 'array',
        ];
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PushUpSession::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PushUpStationLog::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(PushUpStationCommand::class);
    }
}
