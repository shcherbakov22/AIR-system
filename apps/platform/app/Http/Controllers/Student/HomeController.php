<?php

namespace App\Http\Controllers\Student;

use App\Enums\ScheduleWeekday;
use App\Http\Controllers\Controller;
use App\Models\ScheduleRun;
use App\Models\ScheduleTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $student = $request->user()
            ->loadMissing([
                'student.setting',
                'student.scheduleTemplates.entries.taskTemplate',
                'student.scheduleRuns.blocks',
                'student.taskSessions.scheduleRun',
                'student.taskSessions.scheduleRunBlock',
                'student.violations',
                'student.penaltyAccount',
            ])
            ->student;

        $activeTaskSession = $student?->taskSessions
            ? $student->taskSessions
                ->firstWhere('status', 'active')
            : null;

        $activeScheduleRun = null;

        if ($student?->scheduleRuns) {
            $activeScheduleRun = $student->scheduleRuns->firstWhere('status', 'active')
                ?? $student->scheduleRuns->firstWhere('status', 'paused');
        }

        $nextScheduleRunBlock = null;
        $pausedScheduleRunBlock = null;

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
            'student' => [
                'display_name' => $student?->display_name ?? $request->user()->name,
                'status' => $student?->status ?? 'pending',
                'notes' => $student?->notes,
            ],
            'studentCapabilities' => [
                'can_manage_own_schedule' => $student?->canManageOwnSchedule() ?? true,
                'can_use_ad_hoc_timer' => $student?->canUseAdHocTimer() ?? true,
            ],
            'penaltySummary' => [
                'current_balance_units' => $student?->penaltyAccount?->currentBalanceUnits() ?? 0,
                'open_violations' => $student?->violations
                    ? $student->violations->where('status', 'open')->count()
                    : 0,
            ],
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
                    'started_at' => $activeTaskSession->started_at?->toAtomString(),
                    'started_at_label' => $activeTaskSession->started_at?->locale(app()->getLocale())->translatedFormat('d M, H:i'),
                    'source_type' => $activeTaskSession->schedule_run_id
                        ? 'schedule'
                        : ($activeTaskSession->task_assignment_id ? 'assignment' : 'ad_hoc'),
                    'schedule_run_id' => $activeTaskSession->schedule_run_id,
                    'schedule_run_name' => $activeTaskSession->scheduleRun?->schedule_name_snapshot,
                    'schedule_run_block_id' => $activeTaskSession->schedule_run_block_id,
                    'schedule_run_block_position' => $activeTaskSession->scheduleRunBlock?->position,
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
                        ->map(fn ($block) => [
                            'id' => $block->id,
                            'position' => $block->position,
                            'status' => $block->status,
                            'start_time' => $block->start_time_snapshot,
                            'duration_minutes' => $block->duration_minutes_snapshot,
                            'notes' => $block->entry_notes_snapshot,
                            'is_next' => $nextScheduleRunBlock?->id === $block->id,
                            'task' => [
                                'title' => $block->task_title_snapshot,
                                'summary' => $block->task_summary_snapshot,
                                'instructions' => $block->task_instructions_snapshot,
                            ],
                        ])
                        ->all(),
                ]
                : null,
            'weeklyScheduleTemplates' => $student?->scheduleTemplates
                ? $student->scheduleTemplates
                    ->filter(fn (ScheduleTemplate $scheduleTemplate) => $scheduleTemplate->is_active)
                    ->sortBy(fn (ScheduleTemplate $scheduleTemplate) => sprintf(
                        '%02d-%s-%010d',
                        $scheduleTemplate->weekday?->sortOrder() ?? 99,
                        $scheduleTemplate->entries->first()?->start_time ?? '23:59:59',
                        $scheduleTemplate->id,
                    ))
                    ->values()
                    ->map(fn (ScheduleTemplate $scheduleTemplate) => [
                        'id' => $scheduleTemplate->id,
                        'name' => $scheduleTemplate->name,
                        'weekday' => [
                            'value' => $scheduleTemplate->weekday?->value ?? ScheduleWeekday::Monday->value,
                            'label' => $scheduleTemplate->weekday?->label() ?? ScheduleWeekday::Monday->label(),
                        ],
                        'is_active' => $scheduleTemplate->is_active,
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
        ]);
    }
}
