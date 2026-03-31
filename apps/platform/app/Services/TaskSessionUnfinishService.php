<?php

namespace App\Services;

use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\TaskSession;
use Illuminate\Support\Facades\DB;

class TaskSessionUnfinishService
{
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
                    'schedule_run_block_id' => null,
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
                        ->where('status', '!=', 'completed')
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

            return [
                'success' => true,
                'message' => "Task session {$lockedTaskSession->task_title_snapshot} marked unfinished.",
                'task_session' => $lockedTaskSession->fresh(['taskAssignment', 'scheduleRun', 'scheduleRunBlock']),
            ];
        });
    }
}
