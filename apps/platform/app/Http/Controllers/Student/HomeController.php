<?php

namespace App\Http\Controllers\Student;

use App\Enums\ScheduleWeekday;
use App\Http\Controllers\Controller;
use App\Models\Violation;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\ScheduleTemplate;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Services\AutomaticObserveTheTimeViolationService;
use App\Services\ScheduleRunFinishWindowService;
use App\Services\StudentAssignmentGateService;
use App\Services\StudentCommunicationGateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    protected function actualDurationSeconds(TaskSession $taskSession): int
    {
        $baseDuration = (int) ($taskSession->duration_seconds ?? 0);

        if ($taskSession->status !== 'active' || ! $taskSession->started_at) {
            return $baseDuration;
        }

        return max(0, $baseDuration + $taskSession->started_at->diffInSeconds(now()));
    }

    protected function formatDuration(int $totalSeconds): string
    {
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    protected function scheduleRunBlockPayload(ScheduleRunBlock $block, ?ScheduleRunBlock $nextScheduleRunBlock): array
    {
        $actualDurationSeconds = $block->taskSessions
            ->sum(fn (TaskSession $taskSession) => $this->actualDurationSeconds($taskSession));
        $unfinishedTaskSession = $block->taskSessions
            ->filter(fn (TaskSession $taskSession) => in_array($taskSession->status, ['active', 'completed'], true))
            ->sortByDesc(fn (TaskSession $taskSession) => [
                $taskSession->status === 'active' ? 1 : 0,
                optional($taskSession->started_at)?->timestamp ?? 0,
                $taskSession->id,
            ])
            ->first();

        return [
            'id' => $block->id,
            'position' => $block->position,
            'status' => $block->status,
            'start_time' => $block->start_time_snapshot,
            'duration_minutes' => $block->duration_minutes_snapshot,
            'notes' => $block->entry_notes_snapshot,
            'is_next' => $nextScheduleRunBlock?->id === $block->id,
            'actual_duration_seconds' => $actualDurationSeconds,
            'actual_duration_label' => $this->formatDuration($actualDurationSeconds),
            'unfinished_url' => $unfinishedTaskSession
                ? route('student.task-sessions.unfinished', $unfinishedTaskSession)
                : null,
            'task' => [
                'title' => $block->task_title_snapshot,
                'summary' => $block->task_summary_snapshot,
                'instructions' => $block->task_instructions_snapshot,
            ],
        ];
    }

    protected function scheduleWeekdayValue(mixed $weekday): string
    {
        if ($weekday instanceof ScheduleWeekday) {
            return $weekday->value;
        }

        return is_string($weekday) ? $weekday : ScheduleWeekday::Monday->value;
    }

    protected function scheduleWeekdayLabel(mixed $weekday): string
    {
        if ($weekday instanceof ScheduleWeekday) {
            return $weekday->label();
        }

        if (is_string($weekday)) {
            return ScheduleWeekday::tryFrom($weekday)?->label() ?? $weekday;
        }

        return ScheduleWeekday::Monday->label();
    }

    protected function scheduleWeekdaySortOrder(mixed $weekday): int
    {
        if ($weekday instanceof ScheduleWeekday) {
            return $weekday->sortOrder();
        }

        if (is_string($weekday)) {
            return ScheduleWeekday::tryFrom($weekday)?->sortOrder() ?? 99;
        }

        return 99;
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        AutomaticObserveTheTimeViolationService $automaticViolationService,
        ScheduleRunFinishWindowService $scheduleRunFinishWindowService,
        StudentAssignmentGateService $assignmentGateService,
        StudentCommunicationGateService $communicationGateService,
    ): Response
    {
        $student = $request->user()->student;

        if ($student) {
            $automaticViolationService->evaluate($student);

            $student->load([
                'setting',
                'scheduleTemplates.entries.taskTemplate',
                'scheduleRuns.blocks.taskSessions',
                'taskSessions.scheduleRun',
                'taskSessions.scheduleRunBlock',
                'violations',
            ]);
        }

        $activeTaskSession = $student?->taskSessions
            ? $student->taskSessions
                ->firstWhere('status', 'active')
            : null;
        $pausedTaskSession = $student?->taskSessions
            ? $student->taskSessions
                ->where('status', 'unfinished')
                ->whereNull('schedule_run_id')
                ->sortByDesc('ended_at')
                ->first()
            : null;

        $activeScheduleRun = null;

        if ($student?->scheduleRuns) {
            $activeScheduleRun = $student->scheduleRuns->firstWhere('status', 'active')
                ?? $student->scheduleRuns->firstWhere('status', 'paused');
        }

        $nextScheduleRunBlock = null;
        $pausedScheduleRunBlock = null;
        $openViolations = $student?->violations
            ? $student->violations
                ->where('status', 'open')
                ->sortByDesc('occurred_at')
                ->values()
            : collect();

        if ($activeScheduleRun) {
            $sortedBlocks = $activeScheduleRun->blocks
                ->sortBy('position')
                ->values();

            $hasRunningBlock = $sortedBlocks->contains('status', 'in_progress');
            $pausedScheduleRunBlock = $sortedBlocks->firstWhere('status', 'paused');

            if (! $hasRunningBlock && ! $pausedScheduleRunBlock) {
                $nextScheduleRunBlock = $sortedBlocks->firstWhere('status', 'pending');
            }
        }

        return Inertia::render('Student/Home', [
            'serverNow' => now()->toAtomString(),
            'scheduleFinishWindow' => [
                'can_finish_now' => $scheduleRunFinishWindowService->canManuallyFinish(now()),
                'opens_at_label' => '7:00 PM',
                'closes_at_label' => '8:00 PM',
            ],
            'student' => [
                'display_name' => $student?->display_name ?? $request->user()->name,
                'status' => $student?->status ?? 'pending',
                'notes' => $student?->notes,
            ],
            'studentCapabilities' => [
                'can_manage_own_schedule' => $student?->canManageOwnSchedule() ?? true,
                'can_use_ad_hoc_timer' => $student?->canUseAdHocTimer() ?? true,
            ],
            'violationSummary' => [
                'open_violations' => $openViolations->count(),
            ],
            'communicationGate' => $student
                ? $communicationGateService->payload($student)
                : [
                    'has_unread' => false,
                    'unread_mentor_chat' => null,
                    'unread_announcement' => null,
                ],
            'assignmentGate' => $student
                ? $assignmentGateService->payload($student)
                : [
                    'has_unread' => false,
                    'unread_count' => 0,
                    'latest_unread_assignment' => null,
                ],
            'openViolations' => $openViolations
                ->map(fn (Violation $violation) => [
                    'id' => $violation->id,
                    'rule_title' => $violation->rule_title_snapshot,
                    'push_up_count' => $violation->penalty_units,
                    'occurred_at_label' => $violation->occurred_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                    'start_push_up_url' => route('student.violations.push-up-sessions.store', $violation),
                ])
                ->all(),
            'activeTaskSession' => $activeTaskSession
                ? [
                    'id' => $activeTaskSession->id,
                    'status' => $activeTaskSession->status,
                    'task_assignment_id' => $activeTaskSession->task_assignment_id,
                    'task_title' => $activeTaskSession->task_title_snapshot,
                    'task_summary' => $activeTaskSession->task_summary_snapshot,
                    'task_instructions' => $activeTaskSession->task_instructions_snapshot,
                    'assignment_notes' => $activeTaskSession->assignment_notes_snapshot,
                    'planned_duration_minutes' => $activeTaskSession->planned_duration_minutes,
                    'duration_seconds' => $activeTaskSession->duration_seconds,
                    'started_at' => $activeTaskSession->started_at?->toAtomString(),
                    'started_at_label' => $activeTaskSession->started_at?->locale(app()->getLocale())->translatedFormat('d M, H:i'),
                    'source_type' => $activeTaskSession->schedule_run_id
                        ? 'schedule'
                        : ($activeTaskSession->task_assignment_id ? 'assignment' : 'ad_hoc'),
                    'schedule_run_id' => $activeTaskSession->schedule_run_id,
                    'schedule_run_name' => $activeTaskSession->scheduleRun?->schedule_name_snapshot,
                    'schedule_run_block_id' => $activeTaskSession->schedule_run_block_id,
                    'schedule_run_block_position' => $activeTaskSession->scheduleRunBlock?->position,
                    'unfinished_url' => route('student.task-sessions.unfinished', $activeTaskSession),
                ]
                : null,
            'pausedTaskSession' => $pausedTaskSession
                ? [
                    'id' => $pausedTaskSession->id,
                    'status' => $pausedTaskSession->status,
                    'task_assignment_id' => $pausedTaskSession->task_assignment_id,
                    'task_title' => $pausedTaskSession->task_title_snapshot,
                    'task_summary' => $pausedTaskSession->task_summary_snapshot,
                    'task_instructions' => $pausedTaskSession->task_instructions_snapshot,
                    'assignment_notes' => $pausedTaskSession->assignment_notes_snapshot,
                    'planned_duration_minutes' => $pausedTaskSession->planned_duration_minutes,
                    'duration_seconds' => $pausedTaskSession->duration_seconds,
                    'ended_at' => $pausedTaskSession->ended_at?->toAtomString(),
                    'ended_at_label' => $pausedTaskSession->ended_at?->locale(app()->getLocale())->translatedFormat('d M, H:i'),
                    'resume_url' => route('student.task-sessions.resume', $pausedTaskSession),
                ]
                : null,
            'activeScheduleRun' => $activeScheduleRun
                ? [
                    'id' => $activeScheduleRun->id,
                    'status' => $activeScheduleRun->status,
                    'schedule_name' => $activeScheduleRun->schedule_name_snapshot,
                    'weekday_label' => $activeScheduleRun->schedule_weekday_snapshot,
                    'notes' => $activeScheduleRun->schedule_notes_snapshot,
                    'started_at_label' => $activeScheduleRun->started_at?->locale(app()->getLocale())->translatedFormat('d M, H:i'),
                    'completed_blocks' => $activeScheduleRun->blocks
                        ->where('status', 'completed')
                        ->count(),
                    'total_blocks' => $activeScheduleRun->blocks->count(),
                    'next_block' => $nextScheduleRunBlock
                        ? [
                            'id' => $nextScheduleRunBlock->id,
                            'position' => $nextScheduleRunBlock->position,
                            'task_title' => $nextScheduleRunBlock->task_title_snapshot,
                            'duration_minutes' => $nextScheduleRunBlock->duration_minutes_snapshot,
                        ]
                        : null,
                    'paused_block' => $pausedScheduleRunBlock
                        ? [
                            'id' => $pausedScheduleRunBlock->id,
                            'position' => $pausedScheduleRunBlock->position,
                            'task_title' => $pausedScheduleRunBlock->task_title_snapshot,
                            'duration_minutes' => $pausedScheduleRunBlock->duration_minutes_snapshot,
                        ]
                        : null,
                    'blocks' => $activeScheduleRun->blocks
                        ->sortBy('position')
                        ->values()
                        ->map(fn (ScheduleRunBlock $block) => $this->scheduleRunBlockPayload($block, $nextScheduleRunBlock))
                        ->all(),
                ]
                : null,
            'weeklyScheduleTemplates' => $student?->scheduleTemplates
                ? $student->scheduleTemplates
                    ->sortBy(fn (ScheduleTemplate $scheduleTemplate) => sprintf(
                        '%02d-%s-%010d',
                        $this->scheduleWeekdaySortOrder($scheduleTemplate->weekday),
                        $scheduleTemplate->entries->first()?->start_time ?? '23:59:59',
                        $scheduleTemplate->id,
                    ))
                    ->values()
                    ->map(fn (ScheduleTemplate $scheduleTemplate) => [
                        'id' => $scheduleTemplate->id,
                        'name' => $scheduleTemplate->name,
                        'weekday' => [
                            'value' => $this->scheduleWeekdayValue($scheduleTemplate->weekday),
                            'label' => $this->scheduleWeekdayLabel($scheduleTemplate->weekday),
                        ],
                        'notes' => $scheduleTemplate->notes,
                        'entries' => $scheduleTemplate->entries
                            ->map(fn ($entry) => [
                                'id' => $entry->id,
                                'start_time' => substr((string) $entry->start_time, 0, 5),
                                'duration_minutes' => $entry->duration_minutes,
                                'notes' => $entry->notes,
                                'task' => [
                                    'title' => $entry->resolvedTaskTitle(),
                                    'summary' => $entry->resolvedTaskSummary(),
                                    'instructions' => $entry->resolvedTaskInstructions(),
                                ],
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->all()
                : [],
            'taskTemplates' => TaskTemplate::query()
                ->orderBy('title')
                ->get()
                ->map(fn (TaskTemplate $taskTemplate) => [
                    'id' => $taskTemplate->id,
                    'title' => $taskTemplate->title,
                    'summary' => $taskTemplate->summary,
                    'instructions' => $taskTemplate->instructions,
                    'default_duration_minutes' => $taskTemplate->default_duration_minutes,
                ])
                ->all(),
        ]);
    }
}
