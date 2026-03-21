<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentConsequenceProfile;
use Illuminate\Support\Facades\DB;

class StudentPushUpCounterService
{
    public const DEFAULT_COUNT = 10;

    public function currentCount(Student $student): int
    {
        return $this->ensureProfile($student)->current_push_up_count;
    }

    public function allocateForViolation(Student $student): int
    {
        $profile = $this->lockProfile($student);
        $currentCount = max(0, (int) $profile->current_push_up_count);

        $profile->update([
            'current_push_up_count' => $currentCount + 1,
        ]);

        return $currentCount;
    }

    public function increment(Student $student): int
    {
        $profile = $this->lockProfile($student);
        $nextCount = max(0, (int) $profile->current_push_up_count) + 1;
        $profile->update(['current_push_up_count' => $nextCount]);

        return $nextCount;
    }

    public function decrement(Student $student): int
    {
        $profile = $this->lockProfile($student);
        $nextCount = max(0, (int) $profile->current_push_up_count - 1);
        $profile->update(['current_push_up_count' => $nextCount]);

        return $nextCount;
    }

    public function reset(Student $student): int
    {
        $profile = $this->lockProfile($student);
        $profile->update(['current_push_up_count' => self::DEFAULT_COUNT]);

        return self::DEFAULT_COUNT;
    }

    public function ensureProfile(Student $student): StudentConsequenceProfile
    {
        return $student->consequenceProfile()->firstOrCreate(
            ['student_id' => $student->id],
            [
                'default_push_up_count' => 0,
                'current_push_up_count' => self::DEFAULT_COUNT,
                'rest_duration_seconds' => 0,
                'legacy_owner_user_id' => null,
                'notes' => null,
            ],
        );
    }

    private function lockProfile(Student $student): StudentConsequenceProfile
    {
        $this->ensureProfile($student);

        return StudentConsequenceProfile::query()
            ->where('student_id', $student->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
