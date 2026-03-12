<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\StudentMonitorCapture;
use App\Models\TaskSession;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
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

    protected function studentPayload(Student $student): array
    {
        $activeTaskSession = $student->taskSessions->first();
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
