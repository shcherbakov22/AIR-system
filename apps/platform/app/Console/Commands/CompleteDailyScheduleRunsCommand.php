<?php

namespace App\Console\Commands;

use App\Models\ScheduleRun;
use App\Models\TaskSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteDailyScheduleRunsCommand extends Command
{
    protected $signature = 'schedule-runs:complete-daily-open';

    protected $description = 'Automatically complete open schedules and their active timers at the end of the day.';

    public function handle(): int
    {
        $completedRuns = 0;
        $completedTaskSessions = 0;

        ScheduleRun::query()
            ->whereIn('status', ['active', 'paused'])
            ->orderBy('id')
            ->chunkById(100, function ($runs) use (&$completedRuns, &$completedTaskSessions) {
                foreach ($runs as $run) {
                    DB::transaction(function () use ($run, &$completedRuns, &$completedTaskSessions) {
                        $lockedRun = ScheduleRun::query()
                            ->whereKey($run->id)
                            ->whereIn('status', ['active', 'paused'])
                            ->lockForUpdate()
                            ->first();

                        if (! $lockedRun) {
                            return;
                        }

                        $finishedAt = now();

                        $taskSessions = TaskSession::query()
                            ->where('student_id', $lockedRun->student_id)
                            ->whereIn('status', ['active', 'paused'])
                            ->where(function ($query) use ($lockedRun) {
                                $query->where('schedule_run_id', $lockedRun->id);

                                if ($lockedRun->status === 'paused') {
                                    $query->orWhere(function ($nested) {
                                        $nested->whereNull('schedule_run_id')
                                            ->whereNull('task_assignment_id');
                                    });
                                }
                            })
                            ->lockForUpdate()
                            ->get();

                        foreach ($taskSessions as $taskSession) {
                            $durationSeconds = (int) ($taskSession->duration_seconds ?? 0);

                            if ($taskSession->status === 'active') {
                                $durationSeconds = (int) max(
                                    0,
                                    $durationSeconds + ($taskSession->started_at?->diffInSeconds($finishedAt) ?? 0),
                                );
                            }

                            $taskSession->update([
                                'status' => 'completed',
                                'ended_at' => $finishedAt,
                                'duration_seconds' => $durationSeconds,
                                'completion_notes' => 'Automatically finished at 20:00 end of day.',
                                'stopped_by_user_id' => null,
                            ]);

                            $completedTaskSessions++;
                        }

                        $lockedRun->blocks()
                            ->whereIn('status', ['in_progress', 'paused'])
                            ->update([
                                'status' => 'completed',
                                'completed_at' => $finishedAt,
                            ]);

                        $lockedRun->update([
                            'status' => 'completed',
                            'completed_at' => $finishedAt,
                            'completed_by_user_id' => null,
                        ]);

                        $completedRuns++;
                    });
                }
            });

        $this->info("Completed {$completedRuns} schedule runs and {$completedTaskSessions} task sessions.");

        return self::SUCCESS;
    }
}
