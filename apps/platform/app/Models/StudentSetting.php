<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'can_manage_own_schedule',
        'can_use_ad_hoc_timer',
        'preferred_timezone',
    ];

    protected function casts(): array
    {
        return [
            'can_manage_own_schedule' => 'boolean',
            'can_use_ad_hoc_timer' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
