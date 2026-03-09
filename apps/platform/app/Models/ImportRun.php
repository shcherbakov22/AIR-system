<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_system',
        'source_label',
        'status',
        'started_by_user_id',
        'started_at',
        'finished_at',
        'notes',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    public function startedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function legacyRecordLinks(): HasMany
    {
        return $this->hasMany(LegacyRecordLink::class);
    }

    public function reconciliationIssues(): HasMany
    {
        return $this->hasMany(ImportReconciliationIssue::class);
    }
}
