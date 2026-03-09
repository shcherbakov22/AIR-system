<?php

namespace App\Models;

use App\Enums\ScheduleWeekday;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'name',
        'weekday',
        'is_active',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => ScheduleWeekday::class,
            'is_active' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class)->orderBy('position')->orderBy('start_time');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ScheduleRun::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
