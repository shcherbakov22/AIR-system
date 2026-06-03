<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiOverseerDecision;
use App\Models\RuleDefinition;
use App\Models\ScheduleEntry;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\StudentMonitorCapture;
use App\Models\ScheduleTemplate;
use App\Models\TaskSession;
use App\Models\SpeechAnnouncement;
use App\Models\StudentDevice;
use App\Models\PushUpStation;
use App\Services\DevicePolicyService;
use App\Services\PushUpSessionService;
use App\Services\SpeechAnnouncementPlaybackService;
use App\Services\StudentAppPolicyService;
use App\Services\StudentCommunicationGateService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected SpeechAnnouncementPlaybackService $speechPlaybackService,
        protected DevicePolicyService $devicePolicyService,
        protected StudentAppPolicyService $studentAppPolicyService,
        protected StudentCommunicationGateService $studentCommunicationGateService,
    ) {}

    protected function actualDurationSeconds(TaskSession $taskSession): int
    {
        $baseDuration = (int) ($taskSession->duration_seconds ?? 0);

        if ($taskSession->status !== 'active' || ! $taskSession->started_at) {
            return max(0, $baseDuration);
        }

        return max(0, $baseDuration + $taskSession->started_at->diffInSeconds(now()));
    }

    protected function formatDuration(int $totalSeconds): string
    {
        $safeSeconds = max(0, $totalSeconds);
        $hours = intdiv($safeSeconds, 3600);
        $minutes = intdiv($safeSeconds % 3600, 60);
        $seconds = $safeSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    protected function scheduleBlockActualDurationSeconds(ScheduleRunBlock $block): int
    {
        return (int) $block->taskSessions
            ->map(fn (TaskSession $taskSession) => $this->actualDurationSeconds($taskSession))
            ->max() ?? 0;
    }

    protected function formatStatus(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    protected function capturePayload(?StudentMonitorCapture $capture): ?array
    {
        if (! $capture) {
            return null;
        }

        return [
            'id' => $capture->id,
            'capture_kind' => $capture->capture_kind,
            'captured_at' => $capture->captured_at?->toIso8601String(),
            'captured_at_label' => $capture->captured_at?->format('d M, H:i'),
            'uploaded_at' => $capture->uploaded_at?->toIso8601String(),
            'uploaded_at_label' => $capture->uploaded_at?->format('d M, H:i'),
            'task_title' => $capture->task_title_snapshot,
            'source_label' => $capture->source_label,
            'image_url' => route('admin.student-monitor-captures.show', $capture),
        ];
    }

    protected function activeTaskSessionPayload(?TaskSession $taskSession): ?array
    {
        if (! $taskSession) {
            return null;
        }

        return [
            'id' => $taskSession->id,
            'task_title' => $taskSession->task_title_snapshot,
            'started_at' => $taskSession->started_at?->toIso8601String(),
            'started_at_label' => $taskSession->started_at?->format('d M, H:i'),
            'duration_seconds' => $taskSession->duration_seconds ?? 0,
            'planned_duration_minutes' => $taskSession->planned_duration_minutes,
            'source_type' => $taskSession->schedule_run_id
                ? 'schedule'
                : ($taskSession->task_assignment_id ? 'assignment' : 'custom'),
            'schedule_run' => $taskSession->scheduleRun
                ? [
                    'id' => $taskSession->scheduleRun->id,
                    'name' => $taskSession->scheduleRun->schedule_name_snapshot,
                ]
                : null,
            'schedule_run_block' => $taskSession->scheduleRunBlock
                ? [
                    'position' => $taskSession->scheduleRunBlock->position,
                ]
                : null,
            'unfinished_url' => route('admin.task-sessions.unfinished', $taskSession),
        ];
    }

    protected function idleForPayload(Student $student, ?TaskSession $activeTaskSession, ?ScheduleRun $activeScheduleRun): ?array
    {
        if ($activeTaskSession) {
            return null;
        }

        $latestTaskSession = $student->latestTaskSession;
        $latestTaskActivityAt = $latestTaskSession?->ended_at ?? $latestTaskSession?->started_at;
        $scheduleStartedAt = $activeScheduleRun?->started_at;
        $dayStartedAt = now()->copy()->startOfDay();

        $idleStartedAt = match (true) {
            $latestTaskActivityAt && $scheduleStartedAt
                => $latestTaskActivityAt->greaterThan($scheduleStartedAt) ? $latestTaskActivityAt : $scheduleStartedAt,
            $latestTaskActivityAt !== null => $latestTaskActivityAt,
            $scheduleStartedAt !== null => $scheduleStartedAt,
            default => $dayStartedAt,
        };

        if ($idleStartedAt->lessThan($dayStartedAt)) {
            $idleStartedAt = $dayStartedAt;
        }

        $idleForSeconds = max(0, $idleStartedAt->diffInSeconds(now()));

        return [
            'started_at' => $idleStartedAt->toIso8601String(),
            'started_at_label' => $idleStartedAt->format('d M, H:i'),
            'seconds' => $idleForSeconds,
            'label' => $this->formatDuration($idleForSeconds),
        ];
    }

    protected function scheduleRunBlockPayload(ScheduleRunBlock $block): array
    {
        $actualDurationSeconds = $this->scheduleBlockActualDurationSeconds($block);
        $unfinishedTaskSession = $block->taskSessions
            ->filter(fn (TaskSession $taskSession) => $taskSession->status === 'unfinished')
            ->sortByDesc(fn (TaskSession $taskSession) => [
                optional($taskSession->ended_at)?->timestamp ?? 0,
                $taskSession->id,
            ])
            ->first();
        $actionableTaskSession = $block->taskSessions
            ->filter(fn (TaskSession $taskSession) => in_array($taskSession->status, ['active', 'completed'], true))
            ->sortByDesc(fn (TaskSession $taskSession) => [
                $taskSession->status === 'active' ? 1 : 0,
                optional($taskSession->started_at)?->timestamp ?? 0,
                $taskSession->id,
            ])
            ->first();
        $plannedDurationSeconds = max(0, (int) ($block->duration_minutes_snapshot ?? 0) * 60);
        $isPending = in_array($block->status, ['pending', 'planned'], true);
        $displayDurationLabel = $isPending
            ? $this->formatDuration($plannedDurationSeconds)
            : $this->formatDuration($actualDurationSeconds);
        $statusLabel = $unfinishedTaskSession
            ? 'Unfinished'
            : $this->formatStatus($block->status);

        return [
            'id' => $block->id,
            'position' => $block->position,
            'status' => $block->status,
            'status_label' => $statusLabel,
            'task_title' => $block->task_title_snapshot,
            'planned_duration_minutes' => $block->duration_minutes_snapshot,
            'planned_duration_label' => $this->formatDuration($plannedDurationSeconds),
            'actual_duration_seconds' => $actualDurationSeconds,
            'actual_duration_label' => $this->formatDuration($actualDurationSeconds),
            'display_duration_label' => $displayDurationLabel,
            'display_duration_caption' => $isPending ? 'Planned' : 'Spent',
            'unfinished_url' => $actionableTaskSession
                ? route('admin.task-sessions.unfinished', $actionableTaskSession)
                : null,
            'started_at' => $block->started_at?->toIso8601String(),
            'started_at_label' => $block->started_at?->format('d M, H:i'),
            'completed_at' => $block->completed_at?->toIso8601String(),
            'completed_at_label' => $block->completed_at?->format('d M, H:i'),
        ];
    }

    protected function scheduleRunPayload(?ScheduleRun $scheduleRun, string $sourceLabel): ?array
    {
        if (! $scheduleRun) {
            return null;
        }

        $blocks = $scheduleRun->blocks
            ->sortBy('position')
            ->values()
            ->map(fn (ScheduleRunBlock $block) => $this->scheduleRunBlockPayload($block));

        return [
            'id' => $scheduleRun->id,
            'source_type' => 'run',
            'source_label' => $sourceLabel,
            'name' => $scheduleRun->schedule_name_snapshot,
            'status' => $scheduleRun->status,
            'status_label' => $this->formatStatus($scheduleRun->status),
            'started_at' => $scheduleRun->started_at?->toIso8601String(),
            'started_at_label' => $scheduleRun->started_at?->format('d M, H:i'),
            'completed_at' => $scheduleRun->completed_at?->toIso8601String(),
            'completed_at_label' => $scheduleRun->completed_at?->format('d M, H:i'),
            'completed_blocks' => $scheduleRun->blocks->whereIn('status', ['completed', 'skipped'])->count(),
            'total_blocks' => $scheduleRun->blocks->count(),
            'blocks' => $blocks->all(),
        ];
    }

    protected function activeScheduleRunPayload(?ScheduleRun $scheduleRun): ?array
    {
        return $this->scheduleRunPayload($scheduleRun, 'Active run');
    }

    protected function scheduleTemplateBlockPayload(ScheduleEntry $entry): array
    {
        $plannedDurationSeconds = max(0, (int) ($entry->duration_minutes ?? 0) * 60);

        return [
            'id' => $entry->id,
            'position' => $entry->position,
            'status' => 'planned',
            'status_label' => 'Planned',
            'task_title' => $entry->resolvedTaskTitle(),
            'planned_duration_minutes' => $entry->duration_minutes,
            'planned_duration_label' => $this->formatDuration($plannedDurationSeconds),
            'actual_duration_seconds' => 0,
            'actual_duration_label' => $this->formatDuration(0),
            'display_duration_label' => $this->formatDuration($plannedDurationSeconds),
            'display_duration_caption' => 'Planned',
            'started_at' => null,
            'started_at_label' => null,
            'completed_at' => null,
            'completed_at_label' => null,
        ];
    }

    protected function scheduleTemplatePayload(?ScheduleTemplate $scheduleTemplate): ?array
    {
        if (! $scheduleTemplate) {
            return null;
        }

        $blocks = $scheduleTemplate->entries
            ->sortBy('position')
            ->values()
            ->map(fn (ScheduleEntry $entry) => $this->scheduleTemplateBlockPayload($entry));

        return [
            'id' => $scheduleTemplate->id,
            'source_type' => 'template',
            'source_label' => 'Saved schedule',
            'name' => $scheduleTemplate->name,
            'status' => 'planned',
            'status_label' => 'Planned',
            'started_at' => null,
            'started_at_label' => null,
            'completed_at' => null,
            'completed_at_label' => null,
            'completed_blocks' => 0,
            'total_blocks' => $scheduleTemplate->entries->count(),
            'blocks' => $blocks->all(),
        ];
    }

    protected function pushUpStationPayload(): ?array
    {
        $station = PushUpStation::query()
            ->with([
                'logs' => fn ($query) => $query->latest('id')->limit(8),
                'commands' => fn ($query) => $query->latest('id')->limit(5),
                'sessions' => fn ($query) => $query
                ->with(['student.user'])
                ->whereIn('status', [
                    PushUpSessionService::STATUS_CLAIMED,
                    PushUpSessionService::STATUS_RUNNING,
                ])
                ->latest('id')
                ->limit(1),
            ])
            ->latest('last_seen_at')
            ->latest('id')
            ->first();

        if (! $station) {
            return null;
        }

        $isActive = $station->last_seen_at !== null
            && $station->last_seen_at->greaterThanOrEqualTo(now()->subSeconds(PushUpSessionService::STATION_STALE_AFTER_SECONDS));

        $activeSession = $station->sessions->first();

        return [
            'id' => $station->id,
            'name' => $station->name ?: 'Push-up station',
            'station_key' => $station->station_key,
            'is_active' => $isActive,
            'firmware_version' => $station->firmware_version,
            'ip_address' => $station->ip_address,
            'state' => $station->state,
            'sensor_status' => $station->sensor_status,
            'free_heap' => $station->free_heap,
            'distance' => $station->distance,
            'last_seen_at' => $station->last_seen_at?->toIso8601String(),
            'last_seen_at_label' => $station->last_seen_at?->format('d M, H:i:s'),
            'pending_count' => \App\Models\PushUpSession::query()
                ->where('status', PushUpSessionService::STATUS_PENDING)
                ->count(),
            'session' => $activeSession ? [
                'id' => $activeSession->id,
                'status' => $activeSession->status,
                'student_name' => $activeSession->student->display_name,
                'required_push_ups' => $activeSession->required_push_ups,
            ] : null,
            'logs' => $station->logs->map(fn ($log) => [
                'id' => $log->id,
                'level' => $log->level,
                'event' => $log->event,
                'message' => $log->message,
                'state' => $log->state,
                'distance' => $log->distance,
                'created_at_label' => $log->created_at?->format('H:i:s'),
            ])->all(),
            'commands' => $station->commands->map(fn ($command) => [
                'id' => $command->id,
                'command' => $command->command,
                'status' => $command->status,
                'created_at_label' => $command->created_at?->format('H:i:s'),
            ])->all(),
        ];
    }

    protected function scheduleBoardPayload(Student $student): ?array
    {
        if ($student->activeOrPausedScheduleRun) {
            return $this->scheduleRunPayload($student->activeOrPausedScheduleRun, 'Active run');
        }

        if ($student->latestScheduleRun) {
            return $this->scheduleRunPayload($student->latestScheduleRun, 'Latest run');
        }

        return $this->scheduleTemplatePayload($student->latestScheduleTemplate);
    }

    protected function studentPayload(Student $student): array
    {
        $latestDevice = $student->devices
            ->whereNull('revoked_at')
            ->sortByDesc(fn (StudentDevice $device) => [
                optional($device->last_seen_at)?->timestamp ?? 0,
                $device->id,
            ])
            ->first();
        $latestDeviceActivity = $latestDevice
            ? $this->devicePolicyService->latestActivitySummary($latestDevice)
            : null;
        $activeTaskSession = $student->taskSessions->first();
        $activeScheduleRun = $student->activeOrPausedScheduleRun;
        $scheduleBoard = $this->scheduleBoardPayload($student);
        $idleFor = $this->idleForPayload($student, $activeTaskSession, $activeScheduleRun);
        $violationRuleOptions = RuleDefinition::query()
            ->where('is_active', true)
            ->where(function ($query) use ($student) {
                $query->where('scope', 'global')
                    ->orWhere(function ($studentQuery) use ($student) {
                        $studentQuery->where('scope', 'student')
                            ->where('student_id', $student->id);
                    });
            })
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (RuleDefinition $ruleDefinition) => [
                'id' => $ruleDefinition->id,
                'title' => $ruleDefinition->title,
            ])
            ->all();

        return [
            'id' => $student->id,
            'display_name' => $student->display_name,
            'status' => $student->status,
            'extension_url' => route('admin.extension.show', $student),
            'current_push_up_count' => $student->consequenceProfile?->current_push_up_count ?? \App\Services\StudentPushUpCounterService::DEFAULT_COUNT,
            'user' => [
                'id' => $student->user->id,
                'username' => $student->user->username,
                'last_login_at' => $student->user->last_login_at?->toIso8601String(),
            ],
            'active_schedule_run' => $this->activeScheduleRunPayload($activeScheduleRun),
            'schedule_board' => $scheduleBoard,
            'active_task_session' => $this->activeTaskSessionPayload($activeTaskSession),
            'idle_for' => $idleFor,
            'latest_screen_capture' => $this->capturePayload($student->latestScreenCapture),
            'latest_camera_capture' => $this->capturePayload($student->latestCameraCapture),
            'latest_device_activity' => $latestDevice ? [
                'device_label' => $latestDevice->label,
                'focused_app' => $latestDeviceActivity['focused_app'],
                'open_apps' => $latestDeviceActivity['open_apps'],
                'installed_apps' => $latestDeviceActivity['installed_apps'],
            ] : null,
            'app_control' => [
                ...$this->studentAppPolicyService->policyGroupsForStudent($student),
                'permit_url_template' => route('admin.students.app-policies.permit', [$student, '__APP_POLICY__']),
                'block_url_template' => route('admin.students.app-policies.block', [$student, '__APP_POLICY__']),
            ],
            'ai_overseer_notifications' => (function () use ($student) {
                $decisions = AiOverseerDecision::query()
                    ->where('student_id', $student->id)
                    ->where('status', 'mentor_review')
                    ->latest('created_at')
                    ->limit(3)
                    ->get();

                return [
                    'count' => AiOverseerDecision::query()
                        ->where('student_id', $student->id)
                        ->where('status', 'mentor_review')
                        ->count(),
                    'url' => route('admin.ai-overseer-decisions.index'),
                    'items' => $decisions
                        ->map(fn (AiOverseerDecision $decision) => [
                            'id' => $decision->id,
                            'request_type' => $decision->request_type,
                            'confidence' => $decision->confidence,
                            'message' => $decision->student_message ?: $decision->mentor_summary ?: $decision->reason,
                            'created_at_label' => $decision->created_at?->format('d M, H:i'),
                        ])
                        ->all(),
                ];
            })(),
            'communication_gate' => (function () use ($student) {
                $payload = $this->studentCommunicationGateService->payload($student);
                $payload['unread_student_chats'] = collect($payload['unread_student_chats'] ?? [])
                    ->map(fn (array $message) => $message + [
                        'read_url' => route('admin.students.chat.messages.read', [$student, $message['id']]),
                    ])
                    ->all();

                return $payload + [
                'admin_blocking_message' => $this->studentCommunicationGateService->adminBlockingMessage($student),
                'chat_url' => route('admin.chats.show', $student),
                'read_url' => route('admin.students.chat.read', $student),
            ];
            })(),
            'open_violations' => $student->violations
                ->where('status', 'open')
                ->map(fn ($violation) => [
                    'id' => $violation->id,
                    'rule_title' => $violation->rule_title_snapshot,
                    'push_up_count' => $violation->penalty_units,
                    'occurred_at_label' => $violation->occurred_at?->format('d M, H:i'),
                    'notes' => $violation->notes,
                    'start_push_up_url' => route('admin.violations.push-up-sessions.store', $violation),
                    'false_positive_url' => route('admin.violations.false-positive', $violation),
                ])
                ->values()
                ->all(),
            'violation_rule_options' => $violationRuleOptions,
        ];
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(): Response
    {
        $monitorStudents = Student::query()
            ->with([
                'user',
                'consequenceProfile',
                'activeOrPausedScheduleRun' => fn ($query) => $query
                    ->with([
                        'blocks' => fn ($blockQuery) => $blockQuery
                            ->with([
                                'taskSessions' => fn ($taskSessionQuery) => $taskSessionQuery
                                    ->orderBy('started_at')
                                    ->orderBy('id'),
                            ])
                            ->orderBy('position'),
                    ])
                    ->whereIn('status', ['active', 'paused'])
                    ->latest('started_at')
                    ->latest('id'),
                'latestScheduleRun' => fn ($query) => $query
                    ->with([
                        'blocks' => fn ($blockQuery) => $blockQuery
                            ->with([
                                'taskSessions' => fn ($taskSessionQuery) => $taskSessionQuery
                                    ->orderBy('started_at')
                                    ->orderBy('id'),
                            ])
                            ->orderBy('position'),
                    ])
                    ->latest('started_at')
                    ->latest('id'),
                'latestScheduleTemplate' => fn ($query) => $query
                    ->with([
                        'entries' => fn ($entryQuery) => $entryQuery
                            ->with('taskTemplate')
                            ->orderBy('position'),
                    ])
                    ->latest('updated_at')
                    ->latest('id'),
                'taskSessions' => fn ($query) => $query
                    ->with(['scheduleRun', 'scheduleRunBlock'])
                    ->where('status', 'active')
                    ->latest('started_at'),
                'latestTaskSession',
                'latestScreenCapture',
                'latestCameraCapture',
                'appPolicies',
                'devices' => fn ($query) => $query
                    ->whereNull('revoked_at')
                    ->with([
                        'activityEvents' => fn ($activityQuery) => $activityQuery
                            ->latest('observed_at')
                            ->latest('id')
                            ->limit(8),
                        'installedApps' => fn ($installedAppsQuery) => $installedAppsQuery
                            ->orderBy('display_name')
                            ->limit(200),
                    ])
                    ->latest('last_seen_at')
                    ->latest('id'),
                'violations' => fn ($query) => $query
                    ->where('status', 'open')
                    ->latest('occurred_at'),
            ])
            ->orderBy('display_name')
            ->get()
            ->map(fn (Student $student) => $this->studentPayload($student))
            ->values()
            ->all();

        return Inertia::render('Admin/Dashboard', [
            'serverNow' => now()->toIso8601String(),
            'monitorStudents' => $monitorStudents,
            'pushUpStation' => $this->pushUpStationPayload(),
            'serverSpeech' => [
                'enabled' => $this->speechPlaybackService->isEnabled(),
                'pending_count' => SpeechAnnouncement::query()->whereNull('spoken_at')->count(),
            ],
        ]);
    }
}
