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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TaskSessionController extends Controller
{
    public function stop(
        StopTaskSessionRequest $request,
        TaskSession $taskSession,
        AutomaticObserveTheTimeViolationService $automaticViolationService,
        SpeechAnnouncementService $speechAnnouncementService,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $taskSession, $automaticViolationService, $speechAnnouncementService) {
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

            $automaticViolationService->evaluateStoppedTaskSession(
                $student,
                $lockedTaskSession,
                $endedAt,
                $durationSeconds,
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
