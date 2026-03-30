<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'created_by_user_id',
        'title',
        'body',
        'status',
        'viewed_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function markViewed(): void
    {
        if ($this->status !== 'unread') {
            return;
        }

        $this->forceFill([
            'status' => 'viewed',
            'viewed_at' => now(),
        ])->save();
    }

    public function markInProgress(): void
    {
        if ($this->status !== 'viewed') {
            return;
        }

        $this->forceFill([
            'status' => 'in_progress',
            'started_at' => now(),
        ])->save();
    }

    public function markCompleted(): void
    {
        if ($this->status !== 'handed_in') {
            return;
        }

        $this->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
        ])->save();
    }

    public function markHandedIn(): void
    {
        if ($this->status !== 'in_progress') {
            return;
        }

        $this->forceFill([
            'status' => 'handed_in',
        ])->save();
    }

    public function markIncomplete(): void
    {
        if (! in_array($this->status, ['handed_in', 'completed'], true)) {
            return;
        }

        $this->forceFill([
            'status' => 'in_progress',
            'completed_at' => null,
        ])->save();
    }
}
