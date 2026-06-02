<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushUpStudentProfile extends Model
{
    protected $fillable = [
        'student_id',
        'sample_count',
        'top_distance',
        'down_distance',
        'amplitude',
        'return_distance',
        'noise_cm',
        'average_rep_duration_ms',
        'confidence',
        'last_calibrated_at',
        'metrics',
    ];

    protected function casts(): array
    {
        return [
            'sample_count' => 'integer',
            'top_distance' => 'float',
            'down_distance' => 'float',
            'amplitude' => 'float',
            'return_distance' => 'float',
            'noise_cm' => 'float',
            'average_rep_duration_ms' => 'float',
            'confidence' => 'float',
            'last_calibrated_at' => 'datetime',
            'metrics' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
