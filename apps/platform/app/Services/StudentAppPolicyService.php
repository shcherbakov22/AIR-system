<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAppPolicy;
use App\Models\StudentDevice;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudentAppPolicyService
{
    public const STATUS_PERMITTED = 'permitted';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_BLOCKED = 'blocked';
    public const PENDING_GRACE_SECONDS = 60;

    public function syncOpenApps(StudentDevice $device, array $apps): void
    {
        $student = $device->student;
        $now = now();
        $normalizedApps = collect($apps)
            ->map(fn ($app) => $this->normalizeOpenApp($app))
            ->filter()
            ->values();

        if ($normalizedApps->isEmpty()) {
            return;
        }

        $initialized = (bool) data_get($device->meta ?? [], 'app_policy_initialized_at');
        $existingPolicies = $student->appPolicies()
            ->whereIn('app_key', $normalizedApps->pluck('app_key')->all())
            ->get()
            ->keyBy('app_key');

        foreach ($normalizedApps as $app) {
            /** @var StudentAppPolicy|null $policy */
            $policy = $existingPolicies->get($app['app_key']);
            if ($policy !== null) {
                $policy->forceFill([
                    'app_name' => $app['app_name'],
                    'last_seen_at' => $now,
                ])->save();
                continue;
            }

            $student->appPolicies()->create([
                'app_key' => $app['app_key'],
                'app_name' => $app['app_name'],
                'status' => $initialized ? self::STATUS_PENDING_REVIEW : self::STATUS_PERMITTED,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'grace_deadline_at' => $initialized ? $now->copy()->addSeconds(self::PENDING_GRACE_SECONDS) : null,
            ]);
        }

        if (! $initialized) {
            $meta = $device->meta ?? [];
            $meta['app_policy_initialized_at'] = $now->toAtomString();
            $device->forceFill(['meta' => $meta])->save();
        }
    }

    public function syncInstalledApps(StudentDevice $device, array $apps): void
    {
        $now = now();
        $normalizedApps = collect($apps)
            ->map(fn ($app) => $this->normalizeInstalledApp($app))
            ->filter()
            ->values();

        foreach ($normalizedApps as $app) {
            $device->installedApps()->updateOrCreate(
                ['app_key' => $app['app_key']],
                [
                    'display_name' => $app['display_name'],
                    'display_version' => $app['display_version'],
                    'publisher' => $app['publisher'],
                    'install_location' => $app['install_location'],
                    'first_seen_at' => $device->installedApps()->where('app_key', $app['app_key'])->value('first_seen_at') ?? $now,
                    'last_seen_at' => $now,
                    'meta' => $app['meta'],
                ],
            );
        }
    }

    public function appControlPolicy(StudentDevice $device): array
    {
        $policies = $device->student->appPolicies()->get();

        return [
            'mode' => 'review',
            'blocked_processes' => $policies
                ->filter(fn (StudentAppPolicy $policy) => $policy->status === self::STATUS_BLOCKED
                    || ($policy->status === self::STATUS_PENDING_REVIEW
                        && $policy->grace_deadline_at !== null
                        && $policy->grace_deadline_at->lessThanOrEqualTo(now())))
                ->pluck('app_name')
                ->filter()
                ->values()
                ->all(),
            'blocked_window_patterns' => [],
        ];
    }

    public function policyGroupsForStudent(Student $student): array
    {
        $policies = $student->appPolicies()
            ->orderBy('app_name')
            ->get();

        return [
            'pending_review' => $this->formatPolicies($policies->where('status', self::STATUS_PENDING_REVIEW)),
            'permitted' => $this->formatPolicies($policies->where('status', self::STATUS_PERMITTED)),
            'blocked' => $this->formatPolicies($policies->where('status', self::STATUS_BLOCKED)),
        ];
    }

    public function permit(StudentAppPolicy $policy, int $userId): void
    {
        $policy->forceFill([
            'status' => self::STATUS_PERMITTED,
            'grace_deadline_at' => null,
            'decided_by_user_id' => $userId,
            'decided_at' => now(),
        ])->save();
    }

    public function block(StudentAppPolicy $policy, int $userId): void
    {
        $policy->forceFill([
            'status' => self::STATUS_BLOCKED,
            'grace_deadline_at' => null,
            'decided_by_user_id' => $userId,
            'decided_at' => now(),
        ])->save();
    }

    protected function formatPolicies(Collection $policies): array
    {
        return $policies
            ->map(fn (StudentAppPolicy $policy) => [
                'id' => $policy->id,
                'app_key' => $policy->app_key,
                'app_name' => $policy->app_name,
                'status' => $policy->status,
                'first_seen_at' => $policy->first_seen_at?->toAtomString(),
                'last_seen_at' => $policy->last_seen_at?->toAtomString(),
                'grace_deadline_at' => $policy->grace_deadline_at?->toAtomString(),
            ])
            ->values()
            ->all();
    }

    protected function normalizeOpenApp(mixed $app): ?array
    {
        if (! is_array($app)) {
            return null;
        }

        $appName = trim((string) ($app['app_name'] ?? $app['name'] ?? ''));
        if ($appName === '') {
            return null;
        }

        return [
            'app_key' => $this->appKey($appName),
            'app_name' => $appName,
        ];
    }

    protected function normalizeInstalledApp(mixed $app): ?array
    {
        if (! is_array($app)) {
            return null;
        }

        $displayName = trim((string) ($app['display_name'] ?? $app['app_name'] ?? ''));
        if ($displayName === '') {
            return null;
        }

        $candidateKey = trim((string) ($app['app_name'] ?? $displayName));

        return [
            'app_key' => $this->appKey($candidateKey),
            'display_name' => $displayName,
            'display_version' => $this->nullableString($app['display_version'] ?? null),
            'publisher' => $this->nullableString($app['publisher'] ?? null),
            'install_location' => $this->nullableString($app['install_location'] ?? null),
            'meta' => [
                'source' => $this->nullableString($app['source'] ?? null),
            ],
        ];
    }

    protected function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    protected function appKey(string $appName): string
    {
        return Str::lower(trim($appName));
    }
}
