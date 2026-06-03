<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\PauseScheduleRunRequest;
use App\Http\Requests\Student\StopTaskSessionRequest;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\Violation;
use App\Services\AutomaticObserveTheTimeViolationService;
use App\Services\AttentionTrackingViolationService;
use App\Services\SpeechAnnouncementService;
use App\Services\StudentCommunicationGateService;
use App\Services\TaskSessionSleepService;
use App\Services\TaskSessionUnfinishService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TaskSessionController extends Controller
{
    public function customTimer(
        PauseScheduleRunRequest $request,
        AutomaticObserveTheTimeViolationService $automaticViolationService,
        AttentionTrackingViolationService $attentionTrackingViolationService,
        StudentCommunicationGateService $communicationGateService,
        TaskSessionSleepService $taskSessionSleepService,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;
        $student = $request->user()?->student?->loadMissing('setting');

        if (! $studentId || ! $student) {
            abort(403);
        }

        if (! $student->canUseAdHocTimer()) {
            return redirect()
                ->route('student.home')
                ->with('error', 'Custom timers are disabled for this student.');
        }

        $result = DB::transaction(function () use ($request, $studentId, $automaticViolationService, $attentionTrackingViolationService, $communicationGateService, $taskSessionSleepService) {
            $student = Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();

            $activeScheduleRun = ScheduleRun::query()
                ->where('student_id', $studentId)
                ->whereIn('status', ['active', 'paused'])
                ->lockForUpdate()
                ->first();

            if ($activeScheduleRun) {
                return [
                    'success' => false,
                    'message' => 'Use the schedule custom timer controls while a schedule is open.',
                ];
            }

            $activeTaskSession = TaskSession::query()
                ->with('taskTemplate')
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $activeTaskSession) {
                $automaticViolationService->evaluate($student);
            }

            if ($blockingMessage = $this->blockingViolationMessage($student)) {
                return [
                    'success' => false,
                    'message' => $blockingMessage,
                ];
            }

            if ($blockingMessage = $communicationGateService->blockingMessage($student)) {
                return [
                    'success' => false,
                    'message' => $blockingMessage,
                ];
            }

            $taskTemplate = TaskTemplate::query()
                ->whereKey((int) $request->input('task_template_id'))
                ->firstOrFail();
            $durationMinutes = (int) ($request->input('duration_minutes') ?: $taskTemplate->default_duration_minutes);

            if ($durationMinutes > $taskTemplate->default_duration_minutes) {
                return [
                    'success' => false,
                    'message' => "Custom timer cannot be longer than {$taskTemplate->default_duration_minutes} minutes for {$taskTemplate->title}.",
                ];
            }

            if ($activeTaskSession && $activeTaskSession->schedule_run_id !== null) {
                return [
                    'success' => false,
                    'message' => 'Use the schedule custom timer controls while a schedule task is open.',
                ];
            }

            if ($activeTaskSession && ! $taskSessionSleepService->isSleepingSession($activeTaskSession) && ! $taskTemplate->can_interrupt_schedule) {
                return [
                    'success' => false,
                    'message' => 'This task is not allowed while interrupting a running task.',
                ];
            }

            $startedAt = now();

            if ($activeTaskSession && $taskSessionSleepService->isSleepingSession($activeTaskSession)) {
                $taskSessionSleepService->completeActiveSleepingSession(
                    $studentId,
                    $request->user()->id,
                    $startedAt,
                );
            } elseif ($activeTaskSession) {
                $durationSeconds = (int) max(
                    0,
                    (int) ($activeTaskSession->duration_seconds ?? 0)
                        + ($activeTaskSession->started_at?->diffInSeconds($startedAt) ?? 0),
                );

                $activeTaskSession->update([
                    'status' => 'unfinished',
                    'ended_at' => $startedAt,
                    'duration_seconds' => $durationSeconds,
                    'completion_notes' => 'Paused for a custom timer.',
                    'stopped_by_user_id' => $request->user()->id,
                ]);

                $attentionTrackingViolationService->resetLookAwayCountForStudent($student);
            }

            $automaticViolationService->clearDismissedViolationsForNewTask($student);

            TaskSession::create([
                'student_id' => $studentId,
                'task_assignment_id' => null,
                'schedule_run_id' => null,
                'schedule_run_block_id' => null,
                'task_template_id' => $taskTemplate->id,
                'status' => 'active',
                'task_title_snapshot' => $taskTemplate->title,
                'task_summary_snapshot' => $taskTemplate->summary,
                'task_instructions_snapshot' => $taskTemplate->instructions,
                'assignment_notes_snapshot' => null,
                'planned_duration_minutes' => $durationMinutes,
                'duration_seconds' => 0,
                'started_at' => $startedAt,
                'started_by_user_id' => $request->user()->id,
            ]);

            return [
                'success' => true,
                'message' => $activeTaskSession
                    ? 'Current task paused. Custom timer started.'
                    : 'Custom timer started.',
            ];
        });

        return redirect()
            ->route('student.home')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

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
        AttentionTrackingViolationService $attentionTrackingViolationService,
        SpeechAnnouncementService $speechAnnouncementService,
        TaskSessionSleepService $taskSessionSleepService,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $taskSession, $automaticViolationService, $attentionTrackingViolationService, $speechAnnouncementService, $taskSessionSleepService) {
            $student = Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();

            $lockedTaskSession = TaskSession::query()
                ->with(['taskAssignment', 'scheduleRun', 'scheduleRunBlock', 'taskTemplate'])
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
            $attentionTrackingViolationService->resetLookAwayCountForStudent($student);

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
                    ->whereNotIn('status', ['completed', 'skipped'])
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
                $message .= ' Schedule completed.';
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
                $message .= ' Resume schedule when you\'re ready.';
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
                'resumed_from_task_session_id' => $unfinishedTaskSession->id,
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

    protected function blockingViolationMessage(Student $student): ?string
    {
        /** @var Violation|null $violation */
        $violation = $student->violations()
            ->where('status', 'open')
            ->orderByDesc('occurred_at')
            ->first();

        if (! $violation) {
            return null;
        }

        $occurredAt = $violation->occurred_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i');
        $timeSuffix = $occurredAt ? " ({$occurredAt})" : '';

        return "There is an open violation: {$violation->rule_title_snapshot}{$timeSuffix}. Ask your mentor to close it before starting a custom timer.";
    }
}
