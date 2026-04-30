<?php

namespace App\Services;

use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\Violation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AutomaticObserveTheTimeViolationService
{
    private const RULE_TITLE = 'Observe the time';
    private const GRACE_MINUTES = 5;
    private const MAX_CREATION_DELAY_MINUTES = 2;
    private const AUTO_KEY_PREFIX = 'observe-time:';

    public function __construct(
        private readonly SpeechAnnouncementService $speechAnnouncementService,
        private readonly StudentPushUpCounterService $pushUpCounterService,
        private readonly TaskSessionSleepService $taskSessionSleepService,
        private readonly TaskSessionUnfinishService $taskSessionUnfinishService,
    ) {
    }

    public function evaluate(Student $student): void
    {
        $ruleDefinition = $this->observeTheTimeRule();

        if (! $ruleDefinition) {
            return;
        }

        $activeTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        if ($activeTaskSession) {
            if ($this->taskSessionSleepService->isSleepingSession($activeTaskSession)) {
                return;
            }

            $this->createOverdueTaskViolationIfNeeded($student, $ruleDefinition, $activeTaskSession);

            return;
        }

        $startedScheduleRun = ScheduleRun::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['active', 'paused'])
            ->latest('started_at')
            ->first();

        if ($startedScheduleRun) {
            $this->createIdleScheduleViolationIfNeeded($student, $ruleDefinition, $startedScheduleRun);

            return;
        }

        $this->createNoTaskViolationIfNeeded($student, $ruleDefinition);
    }

    public function evaluateStoppedTaskSession(
        Student $student,
        TaskSession $taskSession,
        CarbonInterface $endedAt,
        int $finalDurationSeconds,
        ?int $baseDurationSeconds = null,
    ): void {
        $ruleDefinition = $this->observeTheTimeRule();

        if (! $ruleDefinition) {
            return;
        }

        $this->createOverdueTaskViolationForElapsedTimeIfNeeded(
            $student,
            $ruleDefinition,
            $taskSession,
            max(0, $finalDurationSeconds),
            $endedAt,
            $baseDurationSeconds,
        );
    }

    public function clearDismissedViolationsForNewTask(Student $student): void
    {
        DB::table('dismissed_automatic_violations')
            ->where('student_id', $student->id)
            ->where('auto_generated_key', 'like', self::AUTO_KEY_PREFIX.'%')
            ->delete();
    }

    private function observeTheTimeRule(): ?RuleDefinition
    {
        return RuleDefinition::query()
            ->where('title', self::RULE_TITLE)
            ->where('scope', 'global')
            ->whereNull('student_id')
            ->where('is_active', true)
            ->first();
    }

    private function createOverdueTaskViolationIfNeeded(
        Student $student,
        RuleDefinition $ruleDefinition,
        TaskSession $taskSession,
    ): void {
        if (! $taskSession->started_at || ! $taskSession->planned_duration_minutes || $taskSession->planned_duration_minutes <= 0) {
            return;
        }

        $baseDurationSeconds = max(0, (int) ($taskSession->duration_seconds ?? 0));
        $plannedSeconds = $taskSession->planned_duration_minutes * 60;
        $thresholdSeconds = $plannedSeconds + (self::GRACE_MINUTES * 60);
        $remainingThresholdSeconds = max(0, $thresholdSeconds - $baseDurationSeconds);

        $violationAt = $taskSession->started_at
            ->copy()
            ->addSeconds($remainingThresholdSeconds);

        if (now()->lt($violationAt)) {
            return;
        }

        $this->createViolationOnce(
            $student,
            $ruleDefinition,
            'observe-time:overtime:session:'.$taskSession->id.':threshold:'.$violationAt->toAtomString(),
            $violationAt,
            'Automatic violation for exceeding the planned task duration by more than 5 minutes.',
        );
    }

    private function createOverdueTaskViolationForElapsedTimeIfNeeded(
        Student $student,
        RuleDefinition $ruleDefinition,
        TaskSession $taskSession,
        int $finalDurationSeconds,
        CarbonInterface $endedAt,
        ?int $baseDurationSeconds = null,
    ): void {
        if (! $taskSession->started_at || ! $taskSession->planned_duration_minutes || $taskSession->planned_duration_minutes <= 0) {
            return;
        }

        $baseDurationSeconds = max(0, (int) ($baseDurationSeconds ?? $taskSession->duration_seconds ?? 0));
        $plannedSeconds = $taskSession->planned_duration_minutes * 60;
        $thresholdSeconds = $plannedSeconds + (self::GRACE_MINUTES * 60);

        if ($finalDurationSeconds < $thresholdSeconds) {
            return;
        }

        $remainingThresholdSeconds = max(0, $thresholdSeconds - $baseDurationSeconds);
        $violationAt = $taskSession->started_at
            ->copy()
            ->addSeconds($remainingThresholdSeconds);

        if ($endedAt->lt($violationAt)) {
            return;
        }

        $this->createViolationOnce(
            $student,
            $ruleDefinition,
            'observe-time:overtime:session:'.$taskSession->id.':threshold:'.$violationAt->toAtomString(),
            $violationAt,
            'Automatic violation for exceeding the planned task duration by more than 5 minutes.',
        );
    }

    private function createIdleScheduleViolationIfNeeded(
        Student $student,
        RuleDefinition $ruleDefinition,
        ScheduleRun $scheduleRun,
    ): void {
        $anchor = TaskSession::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['paused', 'completed'])
            ->whereNotNull('ended_at')
            ->where(function ($query) use ($scheduleRun) {
                $query->where('started_at', '>=', $scheduleRun->started_at)
                    ->orWhere('ended_at', '>=', $scheduleRun->started_at);
            })
            ->latest('ended_at')
            ->first(['id', 'ended_at']);

        $anchorAt = $anchor?->ended_at instanceof CarbonInterface
            ? $anchor->ended_at
            : ($anchor?->ended_at ? Carbon::parse((string) $anchor->ended_at) : $scheduleRun->started_at);

        if (! $anchorAt) {
            return;
        }

        $violationAt = $anchorAt->copy()->addMinutes(self::GRACE_MINUTES);

        if (now()->lt($violationAt)) {
            return;
        }

        if ($this->isStaleViolationThreshold($violationAt)) {
            return;
        }

        if ($anchor?->id && $this->hasOvertimeObserveViolationRecordForTaskSession((int) $anchor->id)) {
            return;
        }

        $this->createViolationOnce(
            $student,
            $ruleDefinition,
            'observe-time:idle:run:'.$scheduleRun->id.':anchor:'.$anchorAt->toAtomString(),
            $violationAt,
            'Automatic violation for staying outside any task for more than 5 minutes after the schedule started.',
        );
    }

    private function createNoTaskViolationIfNeeded(
        Student $student,
        RuleDefinition $ruleDefinition,
    ): void {
        $anchorTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['completed', 'paused', 'unfinished'])
            ->whereNotNull('ended_at')
            ->latest('ended_at')
            ->first(['id', 'ended_at']);

        $anchorAt = $anchorTaskSession?->ended_at instanceof CarbonInterface
            ? $anchorTaskSession->ended_at
            : ($anchorTaskSession?->ended_at ? Carbon::parse((string) $anchorTaskSession->ended_at) : null);

        if (! $anchorAt) {
            return;
        }

        $violationAt = $anchorAt->copy()->addMinutes(self::GRACE_MINUTES);

        if (now()->lt($violationAt)) {
            return;
        }

        if ($this->isStaleViolationThreshold($violationAt)) {
            return;
        }

        if ($anchorTaskSession?->id && $this->hasOvertimeObserveViolationRecordForTaskSession((int) $anchorTaskSession->id)) {
            return;
        }

        $this->createViolationOnce(
            $student,
            $ruleDefinition,
            'observe-time:no-task:student:'.$student->id.':anchor:'.$anchorAt->toAtomString(),
            $violationAt,
            'Automatic violation for staying without any active task for more than 5 minutes.',
        );
    }

    private function createViolationOnce(
        Student $student,
        RuleDefinition $ruleDefinition,
        string $autoGeneratedKey,
        CarbonInterface $occurredAt,
        string $details,
    ): void {
        $wasDismissed = DB::table('dismissed_automatic_violations')
            ->where('auto_generated_key', $autoGeneratedKey)
            ->exists();

        if ($wasDismissed) {
            return;
        }

        $violation = DB::transaction(function () use ($student, $ruleDefinition, $autoGeneratedKey, $occurredAt, $details) {
            $existingViolation = Violation::query()
                ->where('auto_generated_key', $autoGeneratedKey)
                ->lockForUpdate()
                ->first();

            if ($existingViolation) {
                return $existingViolation;
            }

            $existingOpenViolation = Violation::query()
                ->where('student_id', $student->id)
                ->where('status', 'open')
                ->where(function ($query) {
                    $query->whereNull('auto_generated_key')
                        ->orWhere('auto_generated_key', 'not like', self::AUTO_KEY_PREFIX.'%');
                })
                ->lockForUpdate()
                ->first(['id']);

            if ($existingOpenViolation) {
                return null;
            }

            $pushUpCount = $this->pushUpCounterService->allocateForViolation($student);

            return Violation::create([
                'student_id' => $student->id,
                'rule_definition_id' => $ruleDefinition->id,
                'status' => 'open',
                'rule_title_snapshot' => $ruleDefinition->title,
                'penalty_units' => $pushUpCount,
                'occurred_at' => $occurredAt,
                'notes' => $details,
                'reported_by_user_id' => null,
                'auto_generated_key' => $autoGeneratedKey,
            ]);
        });

        if (! $violation) {
            return;
        }

        if ($violation->wasRecentlyCreated) {
            if (str_starts_with($autoGeneratedKey, self::AUTO_KEY_PREFIX.'overtime:')) {
                $this->taskSessionUnfinishService->completeActiveTaskForStudent($student, null);
            } else {
                $this->taskSessionUnfinishService->interruptActiveTaskForStudent($student, null);
            }
            $this->speechAnnouncementService->queueViolation($violation);
        }
    }

    private function hasOvertimeObserveViolationRecordForTaskSession(int $taskSessionId): bool
    {
        $pattern = 'observe-time:overtime:session:'.$taskSessionId.':threshold:%';

        return Violation::query()
            ->where('auto_generated_key', 'like', $pattern)
            ->exists()
            || DB::table('dismissed_automatic_violations')
                ->where('auto_generated_key', 'like', $pattern)
                ->exists();
    }

    private function isStaleViolationThreshold(CarbonInterface $violationAt): bool
    {
        return now()->gt($violationAt->copy()->addMinutes(self::MAX_CREATION_DELAY_MINUTES));
    }
}
