<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use Carbon\CarbonInterface;

class TaskSessionSleepService
{
    private const SLEEPING_TITLE = 'Sleeping';

    public function sleepingTaskTemplate(): ?TaskTemplate
    {
        return TaskTemplate::query()
            ->whereRaw('LOWER(title) = ?', [strtolower(self::SLEEPING_TITLE)])
            ->orderBy('id')
            ->first();
    }

    public function isSleepingSession(?TaskSession $taskSession): bool
    {
        if (! $taskSession) {
            return false;
        }

        if (strcasecmp((string) $taskSession->task_title_snapshot, self::SLEEPING_TITLE) === 0) {
            return true;
        }

        return strcasecmp((string) $taskSession->taskTemplate?->title, self::SLEEPING_TITLE) === 0;
    }

    public function completeActiveSleepingSession(
        int $studentId,
        ?int $actorUserId = null,
        ?CarbonInterface $endedAt = null,
        string $completionNotes = 'Automatically finished when starting another task.',
    ): ?TaskSession {
        $activeTaskSession = TaskSession::query()
            ->with('taskTemplate')
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if (! $this->isSleepingSession($activeTaskSession)) {
            return null;
        }

        $endedAt ??= now();
        $durationSeconds = (int) max(
            0,
            (int) ($activeTaskSession->duration_seconds ?? 0)
                + ($activeTaskSession->started_at?->diffInSeconds($endedAt) ?? 0),
        );

        $activeTaskSession->update([
            'status' => 'completed',
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
            'completion_notes' => $completionNotes,
            'stopped_by_user_id' => $actorUserId,
        ]);

        return $activeTaskSession->fresh();
    }

    public function ensureSleepingSession(
        Student $student,
        ?int $actorUserId = null,
        ?CarbonInterface $startedAt = null,
    ): ?TaskSession {
        $activeTaskSession = TaskSession::query()
            ->with('taskTemplate')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if ($activeTaskSession) {
            return $this->isSleepingSession($activeTaskSession) ? $activeTaskSession : null;
        }

        $sleepingTaskTemplate = $this->sleepingTaskTemplate();

        if (! $sleepingTaskTemplate) {
            return null;
        }

        $startedAt ??= now();

        return TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => $sleepingTaskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => $sleepingTaskTemplate->title,
            'task_summary_snapshot' => $sleepingTaskTemplate->summary,
            'task_instructions_snapshot' => $sleepingTaskTemplate->instructions,
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => $sleepingTaskTemplate->default_duration_minutes,
            'duration_seconds' => 0,
            'started_at' => $startedAt,
            'started_by_user_id' => $actorUserId,
        ]);
    }
}
