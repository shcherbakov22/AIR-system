<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrowserVisitLog extends Model
{
    protected $fillable = [
        'student_id',
        'student_device_id',
        'matched_rule_id',
        'mode',
        'decision',
        'url',
        'host',
        'registrable_domain',
        'page_title',
        'meta',
        'visited_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'visited_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class, 'student_device_id');
    }

    public function matchedRule(): BelongsTo
    {
        return $this->belongsTo(BrowserPolicyRule::class, 'matched_rule_id');
    }
}
