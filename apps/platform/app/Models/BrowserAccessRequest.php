<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrowserAccessRequest extends Model
{
    protected $fillable = [
        'student_id',
        'student_device_id',
        'task_template_id',
        'decided_by_user_id',
        'approved_rule_id',
        'requested_url',
        'host',
        'registrable_domain',
        'reason',
        'status',
        'mentor_note',
        'expires_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'decided_at' => 'datetime',
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

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function approvedRule(): BelongsTo
    {
        return $this->belongsTo(BrowserPolicyRule::class, 'approved_rule_id');
    }
}
