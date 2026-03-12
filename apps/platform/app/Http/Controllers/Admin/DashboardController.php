<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\StudentMonitorCapture;
use App\Models\TaskSession;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
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

        return [
            'id' => $block->id,
            'position' => $block->position,
            'status' => $block->status,
            'task_title' => $block->task_title_snapshot,
            'planned_duration_minutes' => $block->duration_minutes_snapshot,
            'planned_duration_label' => $this->formatDuration($plannedDurationSeconds),
            'actual_duration_seconds' => $actualDurationSeconds,
            'actual_duration_label' => $this->formatDuration($actualDurationSeconds),
            'started_at' => $block->started_at?->toIso8601String(),
            'started_at_label' => $block->started_at?->format('d M, H:i'),
            'completed_at' => $block->completed_at?->toIso8601String(),
            'completed_at_label' => $block->completed_at?->format('d M, H:i'),
        ];
    }

    protected function activeScheduleRunPayload(?ScheduleRun $scheduleRun): ?array
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
            'name' => $scheduleRun->schedule_name_snapshot,
            'status' => $scheduleRun->status,
            'started_at' => $scheduleRun->started_at?->toIso8601String(),
            'started_at_label' => $scheduleRun->started_at?->format('d M, H:i'),
            'completed_blocks' => $scheduleRun->blocks->where('status', 'completed')->count(),
            'total_blocks' => $scheduleRun->blocks->count(),
            'blocks' => $blocks->all(),
        ];
    }

    protected function studentPayload(Student $student): array
    {
        $activeTaskSession = $student->taskSessions->first();
        $activeScheduleRun = $student->scheduleRuns->first();
        $latestCaptureAt = collect([
            $student->latestScreenCapture?->captured_at?->getTimestamp(),
            $student->latestCameraCapture?->captured_at?->getTimestamp(),
        ])->filter()->max() ?? 0;

        return [
            'id' => $student->id,
            'display_name' => $student->display_name,
            'status' => $student->status,
            'sort_key' => [
                'has_active_task' => $activeTaskSession ? 1 : 0,
                'latest_capture_at' => $latestCaptureAt,
            ],
            'user' => [
                'id' => $student->user->id,
                'username' => $student->user->username,
                'last_login_at' => $student->user->last_login_at?->toIso8601String(),
            ],
            'active_schedule_run' => $this->activeScheduleRunPayload($activeScheduleRun),
            'active_task_session' => $this->activeTaskSessionPayload($activeTaskSession),
            'latest_screen_capture' => $this->capturePayload($student->latestScreenCapture),
            'latest_camera_capture' => $this->capturePayload($student->latestCameraCapture),
            'open_violations' => $student->violations
                ->map(fn ($violation) => [
                    'id' => $violation->id,
                    'rule_title' => $violation->rule_title_snapshot,
                    'occurred_at_label' => $violation->occurred_at?->format('d M, H:i'),
                ])
                ->all(),
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
                'scheduleRuns' => fn ($query) => $query
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
            ->sort(function (array $left, array $right): int {
                $activityComparison = $right['sort_key']['has_active_task'] <=> $left['sort_key']['has_active_task'];

                if ($activityComparison !== 0) {
                    return $activityComparison;
                }

                $captureComparison = $right['sort_key']['latest_capture_at'] <=> $left['sort_key']['latest_capture_at'];

                if ($captureComparison !== 0) {
                    return $captureComparison;
                }

                return strcasecmp($left['display_name'], $right['display_name']);
            })
            ->values()
            ->map(function (array $student) {
                unset($student['sort_key']);

                return $student;
            })
            ->all();

        $ruleDefinitions = RuleDefinition::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get()
            ->map(fn (RuleDefinition $ruleDefinition) => [
                'id' => $ruleDefinition->id,
                'title' => $ruleDefinition->title,
            ])
            ->all();

        return Inertia::render('Admin/Dashboard', [
            'serverNow' => now()->toIso8601String(),
            'monitorStudents' => $monitorStudents,
            'ruleDefinitions' => $ruleDefinitions,
        ]);
    }
}
