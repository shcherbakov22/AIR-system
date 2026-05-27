<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentConsequenceProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'default_push_up_count',
        'current_push_up_count',
        'increment_push_up_count_per_violation',
        'rest_duration_seconds',
        'legacy_owner_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'default_push_up_count' => 'integer',
            'current_push_up_count' => 'integer',
            'increment_push_up_count_per_violation' => 'boolean',
            'rest_duration_seconds' => 'integer',
            'legacy_owner_user_id' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
