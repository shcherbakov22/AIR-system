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

            $lockedTaskSession->update([
                'status' => 'paused',
                'ended_at' => $pausedAt,
                'duration_seconds' => $durationSeconds,
                'completion_notes' => null,
                'stopped_by_user_id' => $actorUserId,
            ]);

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

                if ($lockedScheduleRunBlock && in_array($lockedScheduleRunBlock->status, ['in_progress', 'completed'], true)) {
                    $lockedScheduleRunBlock->update([
                        'status' => 'paused',
                        'completed_at' => null,
                    ]);
                }

                if ($lockedScheduleRun && in_array($lockedScheduleRun->status, ['active', 'completed'], true)) {
                    $lockedScheduleRun->update([
                        'status' => 'paused',
                        'completed_at' => null,
                        'completed_by_user_id' => null,
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => "Task session {$lockedTaskSession->task_title_snapshot} marked unfinished.",
                'task_session' => $lockedTaskSession->fresh(['taskAssignment', 'scheduleRun', 'scheduleRunBlock']),
            ];
        });
    }
}
