<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceEnrollmentToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'created_by_user_id',
        'used_by_device_id',
        'device_label',
        'token_hash',
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

    public static function issue(int $studentId, int $createdByUserId, ?string $deviceLabel = null, int $ttlMinutes = 30): array
    {
        $plainTextToken = Str::random(48);

        $record = self::query()->create([
            'student_id' => $studentId,
            'created_by_user_id' => $createdByUserId,
            'device_label' => $deviceLabel,
            'token_hash' => hash('sha256', $plainTextToken),
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function usedByDevice(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class, 'used_by_device_id');
    }
}
