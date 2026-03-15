<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StudentDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'device_key',
        'label',
        'hostname',
        'platform',
        'app_version',
        'token_hash',
        'last_seen_at',
        'last_seen_ip',
        'last_ipv4',
        'last_mac_address',
        'last_gateway_ipv4',
        'network_adapter_name',
        'last_policy_hash',
        'last_network_state',
        'revoked_at',
        'revoked_by_user_id',
        'meta',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_network_state' => 'array',
            'revoked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(DeviceHeartbeat::class);
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(DeviceActivityEvent::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    public function commandResults(): HasMany
    {
        return $this->hasMany(DeviceCommandResult::class);
    }

    public function monitorCaptures(): HasMany
    {
        return $this->hasMany(StudentMonitorCapture::class);
    }

    public function issueToken(): string
    {
        $plainTextToken = Str::random(64);

        $this->forceFill([
            'token_hash' => hash('sha256', $plainTextToken),
        ])->save();

        return $plainTextToken;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public static function findByPlainTextToken(?string $token): ?self
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return self::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->first();
    }
}
