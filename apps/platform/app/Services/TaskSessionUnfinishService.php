<?php

namespace App\Services;

use App\Models\Student;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\StudentSetting;
use App\Models\TaskSession;
use App\Models\Violation;
use Illuminate\Support\Facades\DB;

class TaskSessionUnfinishService
{
    public function __construct(
        private readonly StudentPushUpCounterService $pushUpCounterService,
    ) {}

    public function completeActiveTaskForStudent(Student $student, ?int $actorUserId = null): ?TaskSession
    {
        return DB::transaction(function () use ($student, $actorUserId) {
            $activeTaskSession = TaskSession::query()
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $activeTaskSession) {
                return null;
            }

            return $this->completeTaskSession($activeTaskSession, $actorUserId);
        });
    }

    public function interruptActiveTaskForStudent(Student $student, ?int $actorUserId = null): ?TaskSession
    {
        return DB::transaction(function () use ($student, $actorUserId) {
            $activeTaskSession = TaskSession::query()
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $activeTaskSession) {
                return null;
            }

            $result = $this->markUnfinished($activeTaskSession, $actorUserId);

            if (! ($result['success'] ?? false)) {
                return null;
            }

            return $result['task_session'] ?? null;
        });
    }

    public function completeTaskSession(TaskSession $taskSession, ?int $actorUserId = null): TaskSession
    {
        return DB::transaction(function () use ($taskSession, $actorUserId) {
            $lockedTaskSession = TaskSession::query()
                ->with(['taskAssignment', 'scheduleRun', 'scheduleRunBlock'])
                ->whereKey($taskSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTaskSession->status !== 'active') {
                return $lockedTaskSession;
            }

            $endedAt = now();
            $durationSeconds = max(
                0,
                (int) ($lockedTaskSession->duration_seconds ?? 0) + (int) ($lockedTaskSession->started_at?->diffInSeconds($endedAt) ?? 0),
            );

            $lockedTaskSession->update([
                'status' => 'completed',
                'ended_at' => $endedAt,
                'duration_seconds' => $durationSeconds,
                'completion_notes' => 'Automatically finished after overtime violation.',
                'stopped_by_user_id' => $actorUserId,
            ]);

            if ($lockedTaskSession->taskAssignment && $lockedTaskSession->taskAssignment->status === 'assigned') {
                $lockedTaskSession->taskAssignment->update([
                    'status' => 'completed',
                ]);
            }

            if ($lockedTaskSession->schedule_run_id && $lockedTaskSession->schedule_run_block_id) {
                $lockedScheduleRun = ScheduleRun::query()
                    ->whereKey($lockedTaskSession->schedule_run_id)
                    ->lockForUpdate()
                    ->first();

                $lockedScheduleRunBlock = ScheduleRunBlock::query()
                    ->whereKey($lockedTaskSession->schedule_run_block_id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedScheduleRunBlock && $lockedScheduleRunBlock->status === 'in_progress') {
                    $lockedScheduleRunBlock->update([
                        'status' => 'completed',
                        'completed_at' => $endedAt,
                    ]);
                }

                    if ($lockedScheduleRun && $lockedScheduleRun->status === 'active') {
                        $hasPendingBlocks = $lockedScheduleRun->blocks()
                            ->whereNotIn('status', ['completed', 'skipped'])
                            ->exists();

                    if (! $hasPendingBlocks) {
                        $lockedScheduleRun->update([
                            'status' => 'completed',
                            'completed_at' => $endedAt,
                            'completed_by_user_id' => $actorUserId,
                        ]);
                    }
                }
            }

            $this->resetLookAwayCountForStudentId($lockedTaskSession->student_id);

            return $lockedTaskSession->fresh(['taskAssignment', 'scheduleRun', 'scheduleRunBlock']);
        });
    }

    public function markUnfinished(TaskSession $taskSession, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($taskSession, $actorUserId) {
            $lockedTaskSession = TaskSession::query()
                ->with(['taskAssignment', 'scheduleRun', 'scheduleRunBlock'])
                ->whereKey($taskSession->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedTaskSession->status, ['active', 'completed'], true)) {
                return [
                    'success' => false,
                    'message' => 'This task cannot be marked unfinished.',
                    'task_session' => $lockedTaskSession,
                ];
            }

            $pausedAt = $lockedTaskSession->ended_at ?? now();
            $durationSeconds = max(0, (int) ($lockedTaskSession->duration_seconds ?? 0));

            if ($lockedTaskSession->status === 'active') {
                $pausedAt = now();
                $elapsedSeconds = (int) ($lockedTaskSession->started_at?->diffInSeconds($pausedAt) ?? 0);
                $durationSeconds = max(0, $durationSeconds + $elapsedSeconds);
            }

            if ($lockedTaskSession->taskAssignment && $lockedTaskSession->taskAssignment->status === 'completed') {
                $lockedTaskSession->taskAssignment->update([
                    'status' => 'assigned',
                ]);
            }

            if ($lockedTaskSession->schedule_run_id && $lockedTaskSession->schedule_run_block_id) {
                $lockedScheduleRun = ScheduleRun::query()
                    ->whereKey($lockedTaskSession->schedule_run_id)
                    ->lockForUpdate()
                    ->first();

                $lockedScheduleRunBlock = ScheduleRunBlock::query()
                    ->whereKey($lockedTaskSession->schedule_run_block_id)
                    ->lockForUpdate()
                    ->first();

                $lockedTaskSession->update([
                    'status' => 'completed',
                    'ended_at' => $pausedAt,
                    'duration_seconds' => $durationSeconds,
                    'completion_notes' => 'Marked unfinished for later continuation.',
                    'stopped_by_user_id' => $actorUserId,
                ]);

                TaskSession::create([
                    'student_id' => $lockedTaskSession->student_id,
                    'task_assignment_id' => $lockedTaskSession->task_assignment_id,
                    'schedule_run_id' => null,
                    'schedule_run_block_id' => $lockedTaskSession->schedule_run_block_id,
                    'task_template_id' => $lockedTaskSession->task_template_id,
                    'status' => 'unfinished',
                    'task_title_snapshot' => $lockedTaskSession->task_title_snapshot,
                    'task_summary_snapshot' => $lockedTaskSession->task_summary_snapshot,
                    'task_instructions_snapshot' => $lockedTaskSession->task_instructions_snapshot,
                    'assignment_notes_snapshot' => $lockedTaskSession->assignment_notes_snapshot,
                    'planned_duration_minutes' => $lockedTaskSession->planned_duration_minutes,
                    'started_at' => $lockedTaskSession->started_at,
                    'ended_at' => $pausedAt,
                    'duration_seconds' => $durationSeconds,
                    'completion_notes' => null,
                    'started_by_user_id' => $lockedTaskSession->started_by_user_id,
                    'stopped_by_user_id' => $actorUserId,
                ]);

                if ($lockedScheduleRunBlock && in_array($lockedScheduleRunBlock->status, ['in_progress', 'paused'], true)) {
                    $lockedScheduleRunBlock->update([
                        'status' => 'completed',
                        'completed_at' => $pausedAt,
                    ]);
                }

                if ($lockedScheduleRun && $lockedScheduleRun->status === 'active') {
                    $hasPendingBlocks = $lockedScheduleRun->blocks()
                        ->whereNotIn('status', ['completed', 'skipped'])
                        ->exists();

                    if (! $hasPendingBlocks) {
                        $lockedScheduleRun->update([
                            'status' => 'completed',
                            'completed_at' => $pausedAt,
                            'completed_by_user_id' => $actorUserId,
                        ]);
                    }
                }
            } else {
                $lockedTaskSession->update([
                    'status' => 'unfinished',
                    'ended_at' => $pausedAt,
                    'duration_seconds' => $durationSeconds,
                    'completion_notes' => null,
                    'stopped_by_user_id' => $actorUserId,
                ]);
            }

            $this->deleteTooShortViolationForTaskSession($lockedTaskSession);
            $this->resetLookAwayCountForStudentId($lockedTaskSession->student_id);

            return [
                'success' => true,
                'message' => "Task session {$lockedTaskSession->task_title_snapshot} marked unfinished.",
                'task_session' => $lockedTaskSession->fresh(['taskAssignment', 'scheduleRun', 'scheduleRunBlock']),
            ];
        });
    }

    private function resetLookAwayCountForStudentId(int $studentId): void
    {
        StudentSetting::query()
            ->where('student_id', $studentId)
            ->update([
                'look_away_event_count' => 0,
                'look_away_task_session_id' => null,
            ]);
    }

    private function deleteTooShortViolationForTaskSession(TaskSession $taskSession): void
    {
        $violation = Violation::query()
            ->where('student_id', $taskSession->student_id)
            ->where('status', 'open')
            ->where('rule_title_snapshot', 'Task completed too quickly')
            ->where('auto_generated_key', 'observe-time:too-short:session:'.$taskSession->id)
            ->lockForUpdate()
            ->first();

        if (! $violation) {
            return;
        }

        $violation->delete();

        if ($student = $taskSession->student()->first()) {
            $this->pushUpCounterService->decrement($student);
        }
    }
}
