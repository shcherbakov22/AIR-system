<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushUpSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'violation_id',
        'requested_by_user_id',
        'push_up_station_id',
        'status',
        'required_push_ups',
        'configuration',
        'current_rep',
        'current_set',
        'notes',
        'claimed_at',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'required_push_ups' => 'integer',
            'current_rep' => 'integer',
            'current_set' => 'integer',
            'claimed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(PushUpStation::class, 'push_up_station_id');
    }
}
