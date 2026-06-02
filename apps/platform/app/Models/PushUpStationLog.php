<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushUpStationLog extends Model
{
    protected $fillable = [
        'push_up_station_id',
        'station_key',
        'level',
        'event',
        'message',
        'firmware_version',
        'state',
        'ip_address',
        'free_heap',
        'distance',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'free_heap' => 'integer',
            'distance' => 'integer',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(PushUpStation::class, 'push_up_station_id');
    }
}
