<?php

namespace App\Services;

use App\Models\DeviceActivityEvent;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\TaskSession;
use Illuminate\Support\Arr;

class DevicePolicyService
{
    public function __construct(
        private readonly StudentCommunicationGateService $communicationGateService,
    ) {
    }

    public function buildForDevice(StudentDevice $device): array
    {
        $student = $device->student()
            ->with([
                'user',
                'violations',
                'scheduleRuns.blocks.taskTemplate',
                'taskSessions.taskTemplate',
            ])
            ->firstOrFail();

        $activeTaskSession = $this->resolveActiveTaskSession($student);
        $activeScheduleRun = $student->scheduleRuns
            ->firstWhere('status', 'active')
            ?? $student->scheduleRuns->firstWhere('status', 'paused');
        $communicationGate = $this->communicationGateService->payload($student);
        $openViolations = $student->violations->where('status', 'open')->values();

        $internetPolicy = $this->internetPolicy($student, $activeTaskSession, $activeScheduleRun, $communicationGate['has_unread'], $openViolations->isNotEmpty());

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
                'items' => $openViolations->map(fn ($violation) => [
                    'id' => $violation->id,
                    'rule_title' => $violation->rule_title_snapshot,
                    'occurred_at' => $violation->occurred_at?->toAtomString(),
                ])->all(),
            ],
            'communication_gate' => $communicationGate,
            'internet_policy' => $internetPolicy,
            'app_control' => [
                'mode' => 'blocklist',
                'blocked_processes' => [],
                'blocked_window_patterns' => [],
            ],
            'capture' => [
                'screen_enabled' => true,
                'camera_enabled' => true,
                'screen_interval_seconds' => 30,
                'camera_interval_seconds' => 30,
            ],
            'activity_collection' => [
                'track_open_gui_apps' => true,
                'track_focused_window' => true,
                'track_browser_domain' => true,
            ],
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

    protected function internetPolicy(
        Student $student,
        ?TaskSession $activeTaskSession,
        mixed $activeScheduleRun,
        bool $hasUnreadCommunication,
        bool $hasOpenViolations,
    ): array {
        $blockedByState = $hasUnreadCommunication || $hasOpenViolations;

        if ($blockedByState) {
            return [
                'mode' => 'block_all',
                'internet_allowed' => false,
                'reason' => $hasUnreadCommunication ? 'communication_gate' : 'open_violations',
                'allowed_domains' => [],
            ];
        }

        if ($activeTaskSession) {
            $requiresInternet = $activeTaskSession->taskTemplate?->requires_internet ?? false;

            return [
                'mode' => $requiresInternet ? 'allow_all' : 'block_all',
                'internet_allowed' => $requiresInternet,
                'reason' => $requiresInternet ? 'task_requires_internet' : 'task_blocks_internet',
                'allowed_domains' => [],
            ];
        }

        if ($activeScheduleRun) {
            return [
                'mode' => 'block_all',
                'internet_allowed' => false,
                'reason' => 'open_schedule_without_active_task',
                'allowed_domains' => [],
            ];
        }

        return [
            'mode' => 'allow_all',
            'internet_allowed' => true,
            'reason' => 'no_open_schedule',
            'allowed_domains' => [],
        ];
    }

    public function latestActivitySummary(StudentDevice $device): array
    {
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

        return [
            'focused_app' => $this->eventPayload($latestFocused),
            'open_apps' => $latestOpenApps?->payload['apps'] ?? [],
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
}
