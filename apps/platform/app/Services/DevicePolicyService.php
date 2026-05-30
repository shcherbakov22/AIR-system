<?php

namespace App\Services;

use App\Models\DeviceActivityEvent;
use App\Models\BrowserVisitLog;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\TaskSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DevicePolicyService
{
    private const BROWSER_EXTENSION_STALE_AFTER_MINUTES = 3;
    private const BROWSER_ACTIVITY_FRESH_AFTER_MINUTES = 2;
    private const BROWSER_EXTENSION_WAKE_GRACE_SECONDS = 60;

    public function __construct(
        private readonly StudentCommunicationGateService $communicationGateService,
        private readonly StudentAppPolicyService $studentAppPolicyService,
    ) {
    }

    public function buildForDevice(StudentDevice $device): array
    {
        $student = $device->student()
            ->with([
                'user',
                'setting',
                'violations',
                'activeOrPausedScheduleRun.blocks.taskTemplate',
                'taskSessions.taskTemplate',
            ])
            ->firstOrFail();

        $activeTaskSession = $this->resolveActiveTaskSession($student);
        $activeScheduleRun = $student->activeOrPausedScheduleRun;
        $communicationGate = $this->communicationGateService->payload($student);
        $openViolations = $student->violations->where('status', 'open')->values();
        $internetPolicy = $this->internetPolicy($device);
        $screenCaptureIntervalSeconds = max(5, (int) ($student->setting?->screen_capture_interval_seconds ?? 30));
        $cameraCaptureIntervalSeconds = max(5, (int) ($student->setting?->camera_capture_interval_seconds ?? 30));
        $violationItems = $openViolations->map(fn ($violation) => [
            'id' => $violation->id,
            'rule_title' => $violation->rule_title_snapshot,
            'occurred_at' => $violation->occurred_at?->toAtomString(),
        ])->values();

        return [
            'device' => [
                'id' => $device->id,
                'device_key' => $device->device_key,
                'label' => $device->label,
                'hostname' => $device->hostname,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
            ],
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user?->username,
            ],
            'schedule' => [
                'active_run_id' => $activeScheduleRun?->id,
                'status' => $activeScheduleRun?->status,
                'name' => $activeScheduleRun?->schedule_name_snapshot,
            ],
            'task' => [
                'active_task_session_id' => $activeTaskSession?->id,
                'title' => $activeTaskSession?->task_title_snapshot,
                'planned_duration_minutes' => $activeTaskSession?->planned_duration_minutes,
                'requires_internet' => $activeTaskSession?->taskTemplate?->requires_internet ?? false,
            ],
            'violations' => [
                'open_count' => $openViolations->count(),
                'items' => $violationItems->all(),
            ],
            'violation_app_enforcement' => [
                'kill_gui_apps' => $openViolations->isNotEmpty(),
                'browser_reopen_grace_seconds' => 60,
            ],
            'communication_gate' => $communicationGate,
            'internet_policy' => $internetPolicy,
            'app_control' => [
                ...$this->studentAppPolicyService->appControlPolicy($device),
            ],
            'capture' => [
                'screen_enabled' => true,
                'camera_enabled' => true,
                'screen_interval_seconds' => $screenCaptureIntervalSeconds,
                'camera_interval_seconds' => $cameraCaptureIntervalSeconds,
            ],
            'activity_collection' => [
                'track_open_gui_apps' => true,
                'track_focused_window' => true,
                'track_browser_domain' => true,
            ],
            'browser_extension_enterprise_policy' => $this->browserExtensionEnterprisePolicy($student),
            'commands' => [
                'pending_count' => $device->commands()->where('status', 'pending')->count(),
            ],
            'server_now' => now()->toAtomString(),
        ];
    }

    public function policyHash(array $policy): string
    {
        return hash('sha256', json_encode($policy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function resolveActiveTaskSession(Student $student): ?TaskSession
    {
        return $student->taskSessions
            ->sortByDesc(fn (TaskSession $taskSession) => [
                $taskSession->status === 'active' ? 1 : 0,
                optional($taskSession->started_at)?->timestamp ?? 0,
                $taskSession->id,
            ])
            ->firstWhere('status', 'active');
    }

    protected function internetPolicy(StudentDevice $device): array
    {
        return [
            'mode' => 'allow_all',
            'internet_allowed' => true,
            'reason' => 'internet_control_removed',
            'allowed_domains' => [],
        ];
    }

    protected function browserExtensionEnterprisePolicy(Student $student): array
    {
        if (! $this->hasBrowserExtensionInstallHistory($student)) {
            return [
                'enabled' => false,
                'extension_id' => 'cccijfadcaffnndbpdfdhbncehedgkhb',
                'update_url' => '',
                'platform_url' => '',
                'device_token' => '',
                'chrome_enterprise_enrollment_token' => '',
            ];
        }

        return [
            'enabled' => true,
            'extension_id' => 'cccijfadcaffnndbpdfdhbncehedgkhb',
            'update_url' => route('companion.browser-extension.update-manifest'),
            'platform_url' => url('/'),
            'device_token' => $this->ensureBrowserExtensionSetupToken($student),
            'chrome_enterprise_enrollment_token' => (string) config('services.companion_updates.chrome_enterprise_enrollment_token', ''),
        ];
    }

    protected function hasBrowserExtensionInstallHistory(Student $student): bool
    {
        return StudentDevice::query()
            ->where('student_id', $student->id)
            ->where('platform', 'chrome_extension')
            ->whereNull('revoked_at')
            ->whereNotNull('last_seen_at')
            ->exists();
    }

    protected function ensureBrowserExtensionSetupToken(Student $student): string
    {
        $device = StudentDevice::query()->updateOrCreate(
            [
                'device_key' => 'browser-extension:student:'.$student->id,
            ],
            [
                'student_id' => $student->id,
                'label' => 'Chrome browser extension',
                'hostname' => null,
                'platform' => 'chrome_extension',
                'app_version' => '0.1.0',
                'revoked_at' => null,
            ],
        );

        $setupToken = is_array($device->meta) && is_string($device->meta['setup_token'] ?? null)
            ? $device->meta['setup_token']
            : '';

        if ($setupToken !== '' && hash('sha256', $setupToken) === $device->token_hash) {
            return $setupToken;
        }

        $meta = is_array($device->meta) ? $device->meta : [];
        $token = Str::random(64);
        $meta['setup_token'] = $token;

        $device->forceFill([
            'token_hash' => hash('sha256', $token),
            'meta' => $meta,
            'revoked_at' => null,
        ])->save();

        return $token;
    }

    protected function browserExtensionMissingForStudent(Student $student): bool
    {
        $extensionDevices = StudentDevice::query()
            ->where('student_id', $student->id)
            ->where('platform', 'chrome_extension')
            ->whereNull('revoked_at')
            ->get(['last_seen_at']);

        $latestBrowserObservedAt = $this->latestRecentlyObservedBrowserAt($student);

        if (! $latestBrowserObservedAt) {
            return false;
        }

        $browserOpenObservedAt = $this->oldestRecentlyObservedBrowserAt($student) ?? $latestBrowserObservedAt;

        if ($browserOpenObservedAt->greaterThan(now()->subSeconds(self::BROWSER_EXTENSION_WAKE_GRACE_SECONDS))) {
            return false;
        }

        $staleDeadline = now()->subMinutes(self::BROWSER_EXTENSION_STALE_AFTER_MINUTES);
        $seenDevices = $extensionDevices->filter(
            fn (StudentDevice $device) => $device->last_seen_at instanceof Carbon
        );

        if ($seenDevices->isEmpty()) {
            return false;
        }

        $hasFreshExtension = $seenDevices->contains(
            fn (StudentDevice $device) => $device->last_seen_at instanceof Carbon
                && $device->last_seen_at->greaterThan($staleDeadline)
        );

        if (! $hasFreshExtension) {
            return true;
        }

        return ! $this->hasRecentlyObservedExtensionContent($student);
    }

    protected function hasRecentlyObservedExtensionContent(Student $student): bool
    {
        return BrowserVisitLog::query()
            ->where('student_id', $student->id)
            ->where('visited_at', '>=', now()->subMinutes(self::BROWSER_EXTENSION_STALE_AFTER_MINUTES))
            ->where(function ($query) {
                $query->where('meta->source', 'content_script')
                    ->orWhere(fn ($query) => $query
                        ->where('meta->source', 'chrome_extension')
                        ->where('decision', 'blocked'));
            })
            ->exists();
    }

    protected function hasRecentlyObservedBrowser(Student $student): bool
    {
        return $this->latestRecentlyObservedBrowserAt($student) !== null;
    }

    protected function latestRecentlyObservedBrowserAt(Student $student): ?Carbon
    {
        return $this->recentBrowserActivityQuery($student)
            ->latest('observed_at')
            ->latest('id')
            ->get()
            ->first(fn (DeviceActivityEvent $event) => $this->eventHasBrowserApp($event))
            ?->observed_at;
    }

    protected function oldestRecentlyObservedBrowserAt(Student $student): ?Carbon
    {
        return $this->recentBrowserActivityQuery($student)
            ->oldest('observed_at')
            ->oldest('id')
            ->get()
            ->first(fn (DeviceActivityEvent $event) => $this->eventHasBrowserApp($event))
            ?->observed_at;
    }

    protected function recentBrowserActivityQuery(Student $student)
    {
        return DeviceActivityEvent::query()
            ->where('event_type', 'open_apps')
            ->where('observed_at', '>=', now()->subMinutes(self::BROWSER_ACTIVITY_FRESH_AFTER_MINUTES))
            ->whereHas('studentDevice', fn ($query) => $query
                ->where('student_id', $student->id)
                ->where('platform', '!=', 'chrome_extension')
                ->whereNull('revoked_at')
            );
    }

    protected function eventHasBrowserApp(DeviceActivityEvent $event): bool
    {
        return collect($this->normalizeOpenApps($event->payload['apps'] ?? []))
            ->contains(fn (array $app) => $this->isBrowserAppName((string) ($app['app_name'] ?? '')));
    }

    protected function isBrowserAppName(string $appName): bool
    {
        $normalized = strtolower(trim($appName));

        return in_array($normalized, [
            'chrome.exe',
            'new_chrome.exe',
            'msedge.exe',
            'firefox.exe',
            'brave.exe',
            'bravebrowser.exe',
            'opera.exe',
            'vivaldi.exe',
        ], true);
    }

    public function latestActivitySummary(StudentDevice $device): array
    {
        if ($device->relationLoaded('activityEvents')) {
            $latestFocused = $device->activityEvents
                ->where('event_type', 'focused_app')
                ->sortByDesc(fn (DeviceActivityEvent $event) => [
                    optional($event->observed_at)?->timestamp ?? 0,
                    $event->id,
                ])
                ->first();

            $latestOpenApps = $device->activityEvents
                ->where('event_type', 'open_apps')
                ->sortByDesc(fn (DeviceActivityEvent $event) => [
                    optional($event->observed_at)?->timestamp ?? 0,
                    $event->id,
                ])
                ->first();
        } else {
            $latestFocused = $device->activityEvents()
                ->where('event_type', 'focused_app')
                ->latest('observed_at')
                ->latest('id')
                ->first();

            $latestOpenApps = $device->activityEvents()
                ->where('event_type', 'open_apps')
                ->latest('observed_at')
                ->latest('id')
                ->first();
        }

        return [
            'focused_app' => $this->eventPayload($latestFocused),
            'open_apps' => $this->normalizeOpenApps($latestOpenApps?->payload['apps'] ?? []),
            'installed_apps' => $this->normalizeInstalledApps(
                $device->relationLoaded('installedApps')
                    ? $device->installedApps->sortBy('display_name')->values()->all()
                    : $device->installedApps()->orderBy('display_name')->get()->all()
            ),
        ];
    }

    protected function eventPayload(?DeviceActivityEvent $event): ?array
    {
        if (! $event) {
            return null;
        }

        return [
            'app_name' => $event->app_name,
            'window_title' => $event->window_title,
            'browser_domain' => $event->browser_domain,
            'observed_at' => $event->observed_at?->toAtomString(),
            'payload' => Arr::except($event->payload ?? [], []),
        ];
    }

    public function normalizeOpenApps(array $apps): array
    {
        return collect($apps)
            ->map(function ($app) {
                if (is_string($app)) {
                    return [
                        'app_name' => $app,
                        'window_title' => null,
                    ];
                }

                if (! is_array($app)) {
                    return null;
                }

                $appName = trim((string) ($app['app_name'] ?? $app['name'] ?? ''));
                $windowTitle = trim((string) ($app['window_title'] ?? $app['title'] ?? ''));

                if ($appName === '' && $windowTitle === '') {
                    return null;
                }

                return [
                    'app_name' => $appName !== '' ? $appName : null,
                    'window_title' => $windowTitle !== '' ? $windowTitle : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function normalizeInstalledApps(array $apps): array
    {
        return collect($apps)
            ->map(function ($app) {
                if ($app instanceof \App\Models\StudentDeviceInstalledApp) {
                    return [
                        'display_name' => $app->display_name,
                        'display_version' => $app->display_version,
                        'publisher' => $app->publisher,
                        'install_location' => $app->install_location,
                    ];
                }

                if (! is_array($app)) {
                    return null;
                }

                $displayName = trim((string) ($app['display_name'] ?? ''));
                if ($displayName === '') {
                    return null;
                }

                return [
                    'display_name' => $displayName,
                    'display_version' => trim((string) ($app['display_version'] ?? '')) ?: null,
                    'publisher' => trim((string) ($app['publisher'] ?? '')) ?: null,
                    'install_location' => trim((string) ($app['install_location'] ?? '')) ?: null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
