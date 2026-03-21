<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RuleDefinition;
use App\Models\ScheduleEntry;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\StudentMonitorCapture;
use App\Models\ScheduleTemplate;
use App\Models\TaskSession;
use App\Models\SpeechAnnouncement;
use App\Services\SpeechAnnouncementPlaybackService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected SpeechAnnouncementPlaybackService $speechPlaybackService,
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
        ];
    }

    protected function scheduleRunBlockPayload(ScheduleRunBlock $block): array
    {
        $actualDurationSeconds = $block->taskSessions
            ->sum(fn (TaskSession $taskSession) => $this->actualDurationSeconds($taskSession));
        $plannedDurationSeconds = max(0, (int) ($block->duration_minutes_snapshot ?? 0) * 60);
        $isPending = in_array($block->status, ['pending', 'planned'], true);
        $displayDurationLabel = $isPending
            ? $this->formatDuration($plannedDurationSeconds)
            : $this->formatDuration($actualDurationSeconds);

        return [
            'id' => $block->id,
            'position' => $block->position,
            'status' => $block->status,
            'status_label' => $this->formatStatus($block->status),
            'task_title' => $block->task_title_snapshot,
            'planned_duration_minutes' => $block->duration_minutes_snapshot,
            'planned_duration_label' => $this->formatDuration($plannedDurationSeconds),
            'actual_duration_seconds' => $actualDurationSeconds,
            'actual_duration_label' => $this->formatDuration($actualDurationSeconds),
            'display_duration_label' => $displayDurationLabel,
            'display_duration_caption' => $isPending ? 'Planned' : 'Spent',
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
            'completed_blocks' => $scheduleRun->blocks->where('status', 'completed')->count(),
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
        $activeTaskSession = $student->taskSessions->first();
        $activeScheduleRun = $student->activeOrPausedScheduleRun;
        $scheduleBoard = $this->scheduleBoardPayload($student);
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
            'current_push_up_count' => $student->consequenceProfile?->current_push_up_count ?? \App\Services\StudentPushUpCounterService::DEFAULT_COUNT,
            'user' => [
                'id' => $student->user->id,
                'username' => $student->user->username,
                'last_login_at' => $student->user->last_login_at?->toIso8601String(),
            ],
            'active_schedule_run' => $this->activeScheduleRunPayload($activeScheduleRun),
            'schedule_board' => $scheduleBoard,
            'active_task_session' => $this->activeTaskSessionPayload($activeTaskSession),
            'latest_screen_capture' => $this->capturePayload($student->latestScreenCapture),
            'latest_camera_capture' => $this->capturePayload($student->latestCameraCapture),
            'open_violations' => $student->violations
                ->map(fn ($violation) => [
                    'id' => $violation->id,
                    'rule_title' => $violation->rule_title_snapshot,
                    'push_up_count' => $violation->penalty_units,
                    'occurred_at_label' => $violation->occurred_at?->format('d M, H:i'),
                ])
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
                'latestScreenCapture',
                'latestCameraCapture',
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
            'serverSpeech' => [
                'enabled' => $this->speechPlaybackService->isEnabled(),
                'pending_count' => SpeechAnnouncement::query()->whereNull('spoken_at')->count(),
            ],
        ]);
    }
}
