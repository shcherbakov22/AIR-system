<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ImportReconciliationIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'severity',
        'status',
        'legacy_system',
        'legacy_table',
        'legacy_key',
        'target_type',
        'target_id',
        'summary',
        'details',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
