<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiOverseerMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_overseer_decision_id',
        'user_id',
        'sender',
        'body',
        'is_final_decision',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_final_decision' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(AiOverseerDecision::class, 'ai_overseer_decision_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
