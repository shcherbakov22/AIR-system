<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'role',
        'is_active',
        'last_login_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function assignedTaskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class, 'assigned_by_user_id');
    }

    public function createdScheduleTemplates(): HasMany
    {
        return $this->hasMany(ScheduleTemplate::class, 'created_by_user_id');
    }

    public function createdRuleDefinitions(): HasMany
    {
        return $this->hasMany(RuleDefinition::class, 'created_by_user_id');
    }

    public function reportedViolations(): HasMany
    {
        return $this->hasMany(Violation::class, 'reported_by_user_id');
    }

    public function createdViolationResolutions(): HasMany
    {
        return $this->hasMany(ViolationResolution::class, 'created_by_user_id');
    }

    public function startedTaskSessions(): HasMany
    {
        return $this->hasMany(TaskSession::class, 'started_by_user_id');
    }

    public function stoppedTaskSessions(): HasMany
    {
        return $this->hasMany(TaskSession::class, 'stopped_by_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStudent(): bool
    {
        return $this->role === UserRole::Student;
    }
}
