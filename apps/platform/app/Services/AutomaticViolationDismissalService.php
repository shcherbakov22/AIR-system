<?php

namespace App\Services;

use App\Models\Violation;
use Illuminate\Support\Facades\DB;

class AutomaticViolationDismissalService
{
    public function dismiss(Violation $violation, ?int $dismissedByUserId): void
    {
        if (! $violation->auto_generated_key) {
            return;
        }

        DB::table('dismissed_automatic_violations')->updateOrInsert(
            ['auto_generated_key' => $violation->auto_generated_key],
            [
                'student_id' => $violation->student_id,
                'dismissed_by_user_id' => $dismissedByUserId,
                'dismissed_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
