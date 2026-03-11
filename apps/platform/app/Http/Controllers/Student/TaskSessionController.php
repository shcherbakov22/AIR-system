<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StopTaskSessionRequest;
use App\Http\Requests\Student\StoreTaskSessionRequest;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\TaskAssignment;
use App\Models\TaskSession;
use App\Services\AutomaticObserveTheTimeViolationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TaskSessionController extends Controller
{
    public function store(StoreTaskSessionRequest $request): RedirectResponse
    {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $taskAssignmentId = (int) $request->input('task_assignment_id');

        $result = DB::transaction(function () use ($request, $studentId, $taskAssignmentId) {
            Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();

            $hasActiveSession = TaskSession::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveSession) {
                return [
                    'success' => false,
                    'message' => 'Остановите текущую сессию задания, прежде чем запускать новую.',
                ];
            }

            $taskAssignment = TaskAssignment::query()
                ->with('taskTemplate')
                ->whereKey($taskAssignmentId)
                ->where('student_id', $studentId)
                ->where('status', 'assigned')
                ->firstOrFail();

            TaskSession::create([
                'student_id' => $studentId,
                'task_assignment_id' => $taskAssignment->id,
                'task_template_id' => $taskAssignment->task_template_id,
                'status' => 'active',
                'task_title_snapshot' => $taskAssignment->taskTemplate->title,
                'task_summary_snapshot' => $taskAssignment->taskTemplate->summary,
                'task_instructions_snapshot' => $taskAssignment->taskTemplate->instructions,
                'assignment_notes_snapshot' => $taskAssignment->notes,
                'planned_duration_minutes' => $taskAssignment->taskTemplate->default_duration_minutes,
                'duration_seconds' => 0,
                'started_at' => now(),
                'started_by_user_id' => $request->user()->id,
            ]);

            return [
                'success' => true,
                'message' => "Сессия задания {$taskAssignment->taskTemplate->title} началась.",
            ];
        });

        if (! $result['success']) {
            return redirect()
                ->route('student.home')
                ->with('error', $result['message']);
        }

        return redirect()
            ->route('student.home')
            ->with('success', $result['message']);
    }

    public function stop(
        StopTaskSessionRequest $request,
        TaskSession $taskSession,
        AutomaticObserveTheTimeViolationService $automaticViolationService,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $taskSession, $automaticViolationService) {
            $student = Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();
            $automaticViolationService->evaluate($student);

            $lockedTaskSession = TaskSession::query()
                ->with(['taskAssignment', 'scheduleRun', 'scheduleRunBlock'])
                ->whereKey($taskSession->id)
                ->where('student_id', $studentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTaskSession->status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Эта сессия задания больше не активна.',
                ];
            }

            $endedAt = now();
            $durationSeconds = (int) max(
                0,
                (int) ($lockedTaskSession->duration_seconds ?? 0) + ($lockedTaskSession->started_at?->diffInSeconds($endedAt) ?? 0),
            );

            $lockedTaskSession->update([
                'status' => 'completed',
                'ended_at' => $endedAt,
                'duration_seconds' => $durationSeconds,
                'completion_notes' => null,
                'stopped_by_user_id' => $request->user()->id,
            ]);

            if ($lockedTaskSession->taskAssignment && $lockedTaskSession->taskAssignment->status === 'assigned') {
                $lockedTaskSession->taskAssignment->update([
                    'status' => 'completed',
                ]);
            }

            $completedScheduleName = null;
            $resumePausedScheduleName = null;

            if ($lockedTaskSession->schedule_run_id && $lockedTaskSession->schedule_run_block_id) {
                /** @var ScheduleRun $lockedScheduleRun */
                $lockedScheduleRun = ScheduleRun::query()
                    ->whereKey($lockedTaskSession->schedule_run_id)
                    ->where('student_id', $studentId)
                    ->lockForUpdate()
                    ->firstOrFail();

                /** @var ScheduleRunBlock $lockedScheduleRunBlock */
                $lockedScheduleRunBlock = ScheduleRunBlock::query()
                    ->whereKey($lockedTaskSession->schedule_run_block_id)
                    ->where('schedule_run_id', $lockedScheduleRun->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedScheduleRunBlock->status === 'in_progress') {
                    $lockedScheduleRunBlock->update([
                        'status' => 'completed',
                        'completed_at' => $endedAt,
                    ]);
                }

                $hasPendingBlocks = $lockedScheduleRun->blocks()
                    ->where('status', '!=', 'completed')
                    ->exists();

                if (! $hasPendingBlocks && $lockedScheduleRun->status === 'active') {
                    $lockedScheduleRun->update([
                        'status' => 'completed',
                        'completed_at' => $endedAt,
                        'completed_by_user_id' => $request->user()->id,
                    ]);

                    $completedScheduleName = $lockedScheduleRun->schedule_name_snapshot;
                }
            }

            $message = "Сессия задания {$lockedTaskSession->task_title_snapshot} завершена.";

            if ($completedScheduleName !== null) {
                $message .= " Расписание {$completedScheduleName} завершено.";
            }

            if (
                $completedScheduleName === null
                && $lockedTaskSession->schedule_run_id === null
                && ScheduleRun::query()
                    ->where('student_id', $studentId)
                    ->where('status', 'paused')
                    ->exists()
            ) {
                $resumePausedScheduleName = ScheduleRun::query()
                    ->where('student_id', $studentId)
                    ->where('status', 'paused')
                    ->value('schedule_name_snapshot');
            }

            if ($resumePausedScheduleName !== null) {
                $message .= " Возобновите расписание {$resumePausedScheduleName}, когда будете готовы.";
            }

            return [
                'success' => true,
                'message' => $message,
            ];
        });

        if (! $result['success']) {
            return redirect()
                ->route('student.home')
                ->with('error', $result['message']);
        }

        return redirect()
            ->route('student.home')
            ->with('success', $result['message']);
    }
}
