<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StopTaskSessionRequest;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\TaskSession;
use App\Services\AutomaticObserveTheTimeViolationService;
use App\Services\SpeechAnnouncementService;
use App\Services\TaskSessionSleepService;
use App\Services\TaskSessionUnfinishService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TaskSessionController extends Controller
{
    public function unfinished(
        StopTaskSessionRequest $request,
        TaskSession $taskSession,
        TaskSessionUnfinishService $taskSessionUnfinishService,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $ownedTaskSession = TaskSession::query()
            ->whereKey($taskSession->id)
            ->where('student_id', $studentId)
            ->firstOrFail();

        $result = $taskSessionUnfinishService->markUnfinished($ownedTaskSession, $request->user()->id);

        return redirect()
            ->route('student.home')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function stop(
        StopTaskSessionRequest $request,
        TaskSession $taskSession,
        AutomaticObserveTheTimeViolationService $automaticViolationService,
        SpeechAnnouncementService $speechAnnouncementService,
        TaskSessionSleepService $taskSessionSleepService,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $taskSession, $automaticViolationService, $speechAnnouncementService, $taskSessionSleepService) {
            $student = Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();

            $lockedTaskSession = TaskSession::query()
                ->with(['taskAssignment', 'scheduleRun', 'scheduleRunBlock'])
                ->whereKey($taskSession->id)
                ->where('student_id', $studentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTaskSession->status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'This task session is no longer active.',
                ];
            }

            $endedAt = now();
            $baseDurationSeconds = max(0, (int) ($lockedTaskSession->duration_seconds ?? 0));
            $durationSeconds = (int) max(
                0,
                $baseDurationSeconds + ($lockedTaskSession->started_at?->diffInSeconds($endedAt) ?? 0),
            );

            $lockedTaskSession->update([
                'status' => 'completed',
                'ended_at' => $endedAt,
                'duration_seconds' => $durationSeconds,
                'completion_notes' => null,
                'stopped_by_user_id' => $request->user()->id,
            ]);

            $automaticViolationService->evaluateStoppedTaskSession(
                $student,
                $lockedTaskSession,
                $endedAt,
                $durationSeconds,
                $baseDurationSeconds,
            );

            $speechAnnouncementService->queueTaskSessionFinished($lockedTaskSession, $durationSeconds);

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

            $message = "Task session {$lockedTaskSession->task_title_snapshot} finished.";

            if ($completedScheduleName !== null) {
                $message .= " Schedule {$completedScheduleName} completed.";
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
                $message .= " Resume schedule {$resumePausedScheduleName} when you're ready.";
            }

            $hasActiveOrPausedScheduleRun = ScheduleRun::query()
                ->where('student_id', $studentId)
                ->whereIn('status', ['active', 'paused'])
                ->exists();

            if (! $hasActiveOrPausedScheduleRun && ! $taskSessionSleepService->isSleepingSession($lockedTaskSession)) {
                $taskSessionSleepService->ensureSleepingSession(
                    $student,
                    $request->user()->id,
                    $endedAt,
                );
                $message .= ' Sleeping started.';
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

    public function resume(
        StopTaskSessionRequest $request,
        TaskSession $taskSession,
        TaskSessionSleepService $taskSessionSleepService,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $taskSession, $taskSessionSleepService) {
            $student = Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();

            $unfinishedTaskSession = TaskSession::query()
                ->whereKey($taskSession->id)
                ->where('student_id', $studentId)
                ->where('status', 'unfinished')
                ->whereNull('schedule_run_id')
                ->lockForUpdate()
                ->firstOrFail();

            $taskSessionSleepService->completeActiveSleepingSession(
                $studentId,
                $request->user()->id,
                now(),
            );

            $hasActiveTaskSession = TaskSession::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveTaskSession) {
                return [
                    'success' => false,
                    'message' => 'Stop the current task session before resuming this one.',
                ];
            }

            $hasActiveOrPausedScheduleRun = ScheduleRun::query()
                ->where('student_id', $studentId)
                ->whereIn('status', ['active', 'paused'])
                ->exists();

            if ($hasActiveOrPausedScheduleRun && $unfinishedTaskSession->schedule_run_block_id === null) {
                return [
                    'success' => false,
                    'message' => 'Resume or finish the current schedule before resuming this task.',
                ];
            }

            TaskSession::create([
                'student_id' => $student->id,
                'task_assignment_id' => $unfinishedTaskSession->task_assignment_id,
                'schedule_run_id' => null,
                'schedule_run_block_id' => $unfinishedTaskSession->schedule_run_block_id,
                'task_template_id' => $unfinishedTaskSession->task_template_id,
                'status' => 'active',
                'task_title_snapshot' => $unfinishedTaskSession->task_title_snapshot,
                'task_summary_snapshot' => $unfinishedTaskSession->task_summary_snapshot,
                'task_instructions_snapshot' => $unfinishedTaskSession->task_instructions_snapshot,
                'assignment_notes_snapshot' => $unfinishedTaskSession->assignment_notes_snapshot,
                'planned_duration_minutes' => $unfinishedTaskSession->planned_duration_minutes,
                'duration_seconds' => max(0, (int) ($unfinishedTaskSession->duration_seconds ?? 0)),
                'started_at' => now(),
                'started_by_user_id' => $request->user()->id,
            ]);

            $unfinishedTaskSession->delete();

            return [
                'success' => true,
                'message' => "Task session {$unfinishedTaskSession->task_title_snapshot} resumed.",
            ];
        });

        return redirect()
            ->route('student.home')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
