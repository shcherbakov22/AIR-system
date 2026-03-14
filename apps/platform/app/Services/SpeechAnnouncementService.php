<?php

namespace App\Services;

use App\Models\SpeechAnnouncement;
use App\Models\TaskSession;
use App\Models\Violation;

class SpeechAnnouncementService
{
    public function queueViolation(Violation $violation): SpeechAnnouncement
    {
        $violation->loadMissing('student.user');

        $studentName = $violation->student->display_name ?: $violation->student->user->username;

        return SpeechAnnouncement::create([
            'student_id' => $violation->student_id,
            'violation_id' => $violation->id,
            'kind' => 'violation',
            'message' => sprintf('%s got a %s violation.', $studentName, $violation->rule_title_snapshot),
            'meta' => [
                'rule_title' => $violation->rule_title_snapshot,
                'student_name' => $studentName,
            ],
        ]);
    }

    public function queueTaskSessionFinished(TaskSession $taskSession, int $durationSeconds): SpeechAnnouncement
    {
        $taskSession->loadMissing('student.user');

        $studentName = $taskSession->student->display_name ?: $taskSession->student->user->username;
        $minutesSpent = max(1, (int) floor(max(0, $durationSeconds) / 60));

        return SpeechAnnouncement::create([
            'student_id' => $taskSession->student_id,
            'task_session_id' => $taskSession->id,
            'kind' => 'task_finished',
            'message' => sprintf(
                '%s finished %s in %d minutes.',
                $studentName,
                $taskSession->task_title_snapshot,
                $minutesSpent,
            ),
            'meta' => [
                'task_title' => $taskSession->task_title_snapshot,
                'student_name' => $studentName,
                'minutes_spent' => $minutesSpent,
                'schedule_run_block_id' => $taskSession->schedule_run_block_id,
            ],
        ]);
    }
}
