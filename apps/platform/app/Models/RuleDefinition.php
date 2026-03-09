<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuleDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'scope',
        'student_id',
        'default_penalty_units',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'default_penalty_units' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }
}
