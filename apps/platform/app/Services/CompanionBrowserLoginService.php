<?php

namespace App\Services;

use App\Models\StudentDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CompanionBrowserLoginService
{
    private const CACHE_PREFIX = 'companion-browser-login:';
    private const TTL_SECONDS = 300;

    public function issueUrl(StudentDevice $device, ?string $redirectPath = null): string
    {
        $token = Str::random(64);

        Cache::put(
            self::CACHE_PREFIX.$token,
            [
                'student_device_id' => $device->id,
                'redirect_path' => $this->normalizeRedirectPath($redirectPath),
            ],
            now()->addSeconds(self::TTL_SECONDS),
        );

        return route('companion.browser-login.consume', ['token' => $token]);
    }

    public function consume(string $token): ?array
    {
        $cacheKey = self::CACHE_PREFIX.$token;
        $payload = Cache::pull($cacheKey);

        return is_array($payload) ? $payload : null;
    }

    private function normalizeRedirectPath(?string $redirectPath): string
    {
        $redirectPath = trim((string) $redirectPath);

        if ($redirectPath === '' || ! str_starts_with($redirectPath, '/')) {
            return route('student.home', absolute: false);
        }

        return $redirectPath;
    }
}
