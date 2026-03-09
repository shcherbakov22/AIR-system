<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdgeClientHeartbeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'edge_client_id',
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

    public function edgeClient(): BelongsTo
    {
        return $this->belongsTo(EdgeClient::class);
    }
}
