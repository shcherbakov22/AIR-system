<?php

namespace App\Services;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ViolationFalsePositiveService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly AutomaticViolationDismissalService $automaticViolationDismissalService,
        private readonly StudentPushUpCounterService $pushUpCounterService,
    ) {
    }

    public function markFalsePositive(Violation $violation, User $actor, ?string $notes = null): Violation
    {
        return DB::transaction(function () use ($violation, $actor, $notes) {
            $lockedViolation = Violation::query()
                ->with('student')
                ->whereKey($violation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedViolation->status !== 'open') {
                throw new RuntimeException('This violation is already closed.');
            }

            $lockedViolation->update(['status' => 'false_positive']);
            $this->automaticViolationDismissalService->dismiss($lockedViolation, $actor->id);

            $resolutionNotes = $notes ?: ($actor->isStudent()
                ? 'Student marked this violation as a false positive.'
                : 'Mentor marked this violation as a false positive.');

            $lockedViolation->resolutions()->create([
                'action' => 'false_positive',
                'notes' => $resolutionNotes,
                'recorded_at' => now(),
                'created_by_user_id' => $actor->id,
            ]);

            $this->activityLogService->log(
                'violations',
                $actor->isStudent() ? 'student_false_positive_claimed' : 'mentor_false_positive_marked',
                ($actor->isStudent() ? 'Student claimed false positive: ' : 'Mentor marked false positive: ')
                    .$lockedViolation->rule_title_snapshot,
                $lockedViolation->student_id,
                $actor->id,
                $lockedViolation,
                [
                    'rule_title' => $lockedViolation->rule_title_snapshot,
                    'previous_penalty_units' => $lockedViolation->penalty_units,
                    'actor_role' => $actor->role?->value ?? (string) $actor->role,
                ],
            );

            return $lockedViolation->refresh();
        });
    }

    public function reinstateAfterFalseClaim(Violation $violation, User $actor): Violation
    {
        return DB::transaction(function () use ($violation, $actor) {
            $lockedViolation = Violation::query()
                ->with('student')
                ->whereKey($violation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedViolation->status !== 'false_positive') {
                throw new RuntimeException('Only false-positive violations can be returned.');
            }

            $student = $lockedViolation->student;
            $newPenalty = max(1, $this->pushUpCounterService->currentCount($student)) * 4;
            $oldPenalty = $lockedViolation->penalty_units;

            $lockedViolation->update([
                'status' => 'open',
                'penalty_units' => $newPenalty,
            ]);

            if ($lockedViolation->auto_generated_key) {
                DB::table('dismissed_automatic_violations')
                    ->where('auto_generated_key', $lockedViolation->auto_generated_key)
                    ->delete();
            }

            $lockedViolation->resolutions()->create([
                'action' => 'reinstated',
                'notes' => 'Mentor returned a false-positive claim as cheating. Push-ups set to 4x the current counter.',
                'recorded_at' => now(),
                'created_by_user_id' => $actor->id,
            ]);

            $this->activityLogService->log(
                'violations',
                'false_positive_reinstated',
                'False-positive claim rejected: '.$lockedViolation->rule_title_snapshot,
                $lockedViolation->student_id,
                $actor->id,
                $lockedViolation,
                [
                    'rule_title' => $lockedViolation->rule_title_snapshot,
                    'old_penalty_units' => $oldPenalty,
                    'new_penalty_units' => $newPenalty,
                    'multiplier' => 4,
                ],
            );

            return $lockedViolation->refresh();
        });
    }
}
