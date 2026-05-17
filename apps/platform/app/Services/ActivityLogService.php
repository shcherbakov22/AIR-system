<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ActivityLogService
{
    public function log(
        string $category,
        string $action,
        string $description,
        ?int $studentId = null,
        ?int $actorUserId = null,
        ?Model $subject = null,
        array $metadata = [],
    ): ?ActivityLog {
        if (! Schema::hasTable('activity_logs')) {
            return null;
        }

        return ActivityLog::create([
            'occurred_at' => now(),
            'category' => $category,
            'action' => $action,
            'student_id' => $studentId,
            'actor_user_id' => $actorUserId ?: auth()->id(),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => str($description)->limit(255, '')->toString(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
