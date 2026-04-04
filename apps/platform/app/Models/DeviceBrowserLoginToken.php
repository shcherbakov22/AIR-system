<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceBrowserLoginToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_device_id',
        'token_hash',
        'redirect_path',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function issue(int $studentDeviceId, string $redirectPath = '/student/home', int $ttlMinutes = 10): array
    {
        $plainTextToken = Str::random(64);

        $record = self::query()->create([
            'student_device_id' => $studentDeviceId,
            'token_hash' => hash('sha256', $plainTextToken),
            'redirect_path' => $redirectPath,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return [$record, $plainTextToken];
    }

    public static function findActiveByPlainTextToken(?string $token): ?self
    {
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        return self::query()
            ->where('token_hash', hash('sha256', trim($token)))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function studentDevice(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class);
    }
}
