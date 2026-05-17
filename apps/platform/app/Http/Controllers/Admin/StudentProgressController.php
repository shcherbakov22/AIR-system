<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\TaskSession;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StudentProgressController extends Controller
{
    private const MIN_IDLE_GAP_SECONDS = 300;

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

    protected function sessionLogPayload(TaskSession $taskSession): array
    {
        $actualDurationSeconds = $this->actualDurationSeconds($taskSession);

        return [
            'id' => $taskSession->id,
            'status' => $taskSession->status,
            'started_at' => $taskSession->started_at?->toIso8601String(),
            'started_at_label' => $taskSession->started_at?->format('d M, H:i'),
            'ended_at' => $taskSession->ended_at?->toIso8601String(),
            'ended_at_label' => $taskSession->ended_at?->format('d M, H:i'),
            'duration_seconds' => $actualDurationSeconds,
            'duration_label' => $this->formatDuration($actualDurationSeconds),
        ];
    }

    protected function blockPayload(ScheduleRunBlock $block): array
    {
        $sessionLogs = $block->taskSessions
            ->sortBy('started_at')
            ->values()
            ->map(fn (TaskSession $taskSession) => $this->sessionLogPayload($taskSession));

        $actualDurationSeconds = $this->scheduleBlockActualDurationSeconds($block);
        $plannedDurationSeconds = max(0, (int) ($block->duration_minutes_snapshot ?? 0) * 60);
        $deltaSeconds = $actualDurationSeconds - $plannedDurationSeconds;

        $firstStartedAt = $block->taskSessions
            ->filter(fn (TaskSession $taskSession) => $taskSession->started_at !== null)
            ->sortBy('started_at')
            ->first()?->started_at ?? $block->started_at;

        $lastEndedAt = $block->taskSessions
            ->filter(fn (TaskSession $taskSession) => $taskSession->ended_at !== null)
            ->sortByDesc('ended_at')
            ->first()?->ended_at ?? $block->completed_at;

        return [
            'id' => $block->id,
            'position' => $block->position,
            'status' => $block->status,
            'task_title' => $block->task_title_snapshot,
            'planned_duration_minutes' => $block->duration_minutes_snapshot,
            'planned_duration_label' => $this->formatDuration($plannedDurationSeconds),
            'actual_duration_seconds' => $actualDurationSeconds,
            'actual_duration_label' => $this->formatDuration($actualDurationSeconds),
            'delta_seconds' => $deltaSeconds,
            'delta_label' => ($deltaSeconds > 0 ? '+' : '').$this->formatDuration(abs($deltaSeconds)),
            'actual_started_at' => $firstStartedAt?->toIso8601String(),
            'actual_started_at_label' => $firstStartedAt?->format('d M, H:i'),
            'actual_ended_at' => $lastEndedAt?->toIso8601String(),
            'actual_ended_at_label' => $lastEndedAt?->format('d M, H:i'),
            'session_logs' => $sessionLogs->all(),
        ];
    }

    protected function taskSequencePayload(TaskSession $taskSession): array
    {
        $actualDurationSeconds = $this->actualDurationSeconds($taskSession);
        $plannedDurationSeconds = max(0, (int) ($taskSession->planned_duration_minutes ?? 0) * 60);
        $deltaSeconds = $actualDurationSeconds - $plannedDurationSeconds;

        return [
            'id' => $taskSession->id,
            'kind' => 'task',
            'status' => $taskSession->status,
            'task_title' => $taskSession->task_title_snapshot,
            'planned_duration_minutes' => $taskSession->planned_duration_minutes,
            'planned_duration_label' => $this->formatDuration($plannedDurationSeconds),
            'actual_duration_seconds' => $actualDurationSeconds,
            'actual_duration_label' => $this->formatDuration($actualDurationSeconds),
            'delta_seconds' => $deltaSeconds,
            'delta_label' => ($deltaSeconds > 0 ? '+' : '').$this->formatDuration(abs($deltaSeconds)),
            'started_at' => $taskSession->started_at?->toIso8601String(),
            'started_at_label' => $taskSession->started_at?->format('d M, H:i'),
            'ended_at' => $taskSession->ended_at?->toIso8601String(),
            'ended_at_label' => $taskSession->ended_at?->format('d M, H:i'),
            'was_in_schedule' => $taskSession->schedule_run_block_id !== null,
            'block_position' => $taskSession->scheduleRunBlock?->position,
            'unfinished_url' => in_array($taskSession->status, ['active', 'completed'], true)
                ? route('admin.task-sessions.unfinished', $taskSession)
                : null,
        ];
    }

    protected function idleGapPayload(array $previousTask, array $nextTask): array
    {
        $startedAt = $previousTask['ended_at'] ?? $previousTask['started_at'] ?? null;
        $endedAt = $nextTask['started_at'] ?? null;

        if (! $startedAt || ! $endedAt) {
            throw new \InvalidArgumentException('Idle gap payload requires bounded timestamps.');
        }

        $startedAtMoment = now()->parse($startedAt);
        $endedAtMoment = now()->parse($endedAt);
        $durationSeconds = max(0, $startedAtMoment->diffInSeconds($endedAtMoment));

        return [
            'id' => sprintf('gap-%s-%s', $previousTask['id'], $nextTask['id']),
            'kind' => 'idle_gap',
            'status' => 'idle_gap',
            'task_title' => 'Idle gap',
            'planned_duration_minutes' => 0,
            'planned_duration_label' => '00:00',
            'actual_duration_seconds' => $durationSeconds,
            'actual_duration_label' => $this->formatDuration($durationSeconds),
            'delta_seconds' => $durationSeconds,
            'delta_label' => $this->formatDuration($durationSeconds),
            'started_at' => $startedAtMoment->toIso8601String(),
            'started_at_label' => $startedAtMoment->format('d M, H:i'),
            'ended_at' => $endedAtMoment->toIso8601String(),
            'ended_at_label' => $endedAtMoment->format('d M, H:i'),
            'was_in_schedule' => false,
            'block_position' => null,
            'unfinished_url' => null,
        ];
    }

    protected function withIdleGaps(Collection $taskSequence): Collection
    {
        $withGaps = collect();
        $previousTask = null;

        foreach ($taskSequence as $task) {
            if (
                $previousTask
                && ! empty($previousTask['ended_at'])
                && ! empty($task['started_at'])
            ) {
                $previousEndedAt = now()->parse($previousTask['ended_at']);
                $currentStartedAt = now()->parse($task['started_at']);

                if (
                    $currentStartedAt->greaterThan($previousEndedAt)
                    && $previousEndedAt->diffInSeconds($currentStartedAt) > self::MIN_IDLE_GAP_SECONDS
                ) {
                    $withGaps->push($this->idleGapPayload($previousTask, $task));
                }
            }

            $withGaps->push($task);

            if (($task['kind'] ?? 'task') === 'task') {
                $previousTask = $task;
            }
        }

        return $withGaps->values();
    }

    protected function taskSessionsForRun(ScheduleRun $scheduleRun): Collection
    {
        $runStartedAt = $scheduleRun->started_at;

        if (! $runStartedAt) {
            return $scheduleRun->taskSessions;
        }

        $runEndedAt = $scheduleRun->completed_at ?? now();

        $externalTaskSessions = TaskSession::query()
            ->with('scheduleRunBlock')
            ->where('student_id', $scheduleRun->student_id)
            ->whereNull('schedule_run_id')
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $runEndedAt)
            ->where(function ($query) use ($runStartedAt): void {
                $query
                    ->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $runStartedAt);
            })
            ->get();

        return $scheduleRun->taskSessions
            ->concat($externalTaskSessions)
            ->unique('id')
            ->sortBy([
                ['started_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    protected function runPayload(ScheduleRun $scheduleRun): array
    {
        $blocks = $scheduleRun->blocks
            ->sortBy('position')
            ->values()
            ->map(fn (ScheduleRunBlock $block) => $this->blockPayload($block));

        $taskSequence = $this->withIdleGaps($this->taskSessionsForRun($scheduleRun)
            ->map(fn (TaskSession $taskSession) => $this->taskSequencePayload($taskSession)));

        $totalActualDurationSeconds = $blocks->sum('actual_duration_seconds');
        $totalPlannedDurationMinutes = $scheduleRun->blocks->sum('duration_minutes_snapshot');

        return [
            'id' => $scheduleRun->id,
            'status' => $scheduleRun->status,
            'schedule_name' => $scheduleRun->schedule_name_snapshot,
            'weekday_label' => $scheduleRun->schedule_weekday_snapshot,
            'started_at' => $scheduleRun->started_at?->toIso8601String(),
            'started_at_label' => $scheduleRun->started_at?->format('d M, H:i'),
            'completed_at' => $scheduleRun->completed_at?->toIso8601String(),
            'completed_at_label' => $scheduleRun->completed_at?->format('d M, H:i'),
            'total_blocks' => $scheduleRun->blocks->count(),
            'completed_blocks' => $scheduleRun->blocks->whereIn('status', ['completed', 'skipped'])->count(),
            'total_planned_minutes' => $totalPlannedDurationMinutes,
            'total_planned_duration_label' => $this->formatDuration($totalPlannedDurationMinutes * 60),
            'total_actual_duration_seconds' => $totalActualDurationSeconds,
            'total_actual_duration_label' => $this->formatDuration($totalActualDurationSeconds),
            'task_sequence' => $taskSequence->all(),
        ];
    }

    public function show(Request $request, Student $student): Response
    {
        $student->loadMissing([
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
                    'taskSessions' => fn ($taskSessionQuery) => $taskSessionQuery
                        ->with('scheduleRunBlock')
                        ->orderBy('started_at')
                        ->orderBy('id'),
                ])
                ->latest('started_at')
                ->latest('id'),
        ]);

        $allRuns = $student->scheduleRuns
            ->map(fn (ScheduleRun $scheduleRun) => $this->runPayload($scheduleRun))
            ->values();

        $availableDays = $allRuns
            ->map(function (array $run): ?string {
                if (! $run['started_at']) {
                    return null;
                }

                return substr($run['started_at'], 0, 10);
            })
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        $selectedDay = $request->string('day')->toString();

        if ($selectedDay === '' || ! $availableDays->contains($selectedDay)) {
            $selectedDay = $availableDays->first() ?? now()->toDateString();
        }

        $mode = $request->string('mode')->toString() === 'summary' ? 'summary' : 'runs';

        $runs = $allRuns
            ->filter(function (array $run) use ($selectedDay): bool {
                if (! $run['started_at']) {
                    return false;
                }

                return str_starts_with($run['started_at'], $selectedDay);
            })
            ->values();

        $taskSummary = $runs
            ->flatMap(fn (array $run) => collect($run['task_sequence'])
                ->filter(fn (array $task) => ($task['kind'] ?? 'task') === 'task'))
            ->groupBy('task_title')
            ->map(function ($blocks, string $taskTitle): array {
                $totalActualSeconds = $blocks->sum('actual_duration_seconds');
                $totalPlannedMinutes = $blocks->sum('planned_duration_minutes');
                $completedBlocks = $blocks->whereIn('status', ['completed', 'skipped'])->count();

                return [
                    'task_title' => $taskTitle,
                    'blocks' => $blocks->count(),
                    'completed_blocks' => $completedBlocks,
                    'total_planned_minutes' => $totalPlannedMinutes,
                    'total_planned_duration_label' => $this->formatDuration($totalPlannedMinutes * 60),
                    'total_actual_duration_seconds' => $totalActualSeconds,
                    'total_actual_duration_label' => $this->formatDuration($totalActualSeconds),
                ];
            })
            ->sortByDesc('total_actual_duration_seconds')
            ->values();

        return Inertia::render('Admin/Students/Progress', [
            'serverNow' => now()->toIso8601String(),
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
                'status' => $student->status,
            ],
            'filters' => [
                'mode' => $mode,
                'day' => $selectedDay,
            ],
            'available_days' => $availableDays
                ->map(fn (string $day) => [
                    'value' => $day,
                    'label' => date('d M Y', strtotime($day)),
                ])
                ->all(),
            'summary' => [
                'schedule_runs' => $runs->count(),
                'completed_blocks' => $runs->sum('completed_blocks'),
                'total_blocks' => $runs->sum('total_blocks'),
                'time_in_schedule_seconds' => $runs->sum('total_actual_duration_seconds'),
                'time_in_schedule_label' => $this->formatDuration($runs->sum('total_actual_duration_seconds')),
                'active_schedule_name' => $runs->firstWhere('status', 'active')['schedule_name']
                    ?? $runs->firstWhere('status', 'paused')['schedule_name']
                    ?? null,
            ],
            'runs' => $runs->all(),
            'task_summary' => $taskSummary->all(),
        ]);
    }
}
