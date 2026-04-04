<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentSetting;

class StudentAttentionStateService
{
    public const DEFAULT_LOOK_AWAY_THRESHOLD = 3;

    public function ensureSetting(Student $student): StudentSetting
    {
        return $student->setting()->firstOrCreate(
            ['student_id' => $student->id],
            [
                'can_manage_own_schedule' => true,
                'can_use_ad_hoc_timer' => true,
                'preferred_timezone' => null,
                'look_away_event_threshold' => self::DEFAULT_LOOK_AWAY_THRESHOLD,
                'look_away_event_count' => 0,
                'look_away_task_session_id' => null,
            ],
        );
    }

    public function resetLookAwayCountForStudent(Student $student): void
    {
        $this->ensureSetting($student);

        StudentSetting::query()
            ->where('student_id', $student->id)
            ->update([
                'look_away_event_count' => 0,
                'look_away_task_session_id' => null,
            ]);
    }
}
