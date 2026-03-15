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
    private const AUTO_KEY_PREFIX = 'observe-time:';

    public function __construct(
        private readonly SpeechAnnouncementService $speechAnnouncementService,
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
            $this->createOverdueTaskViolationIfNeeded($student, $ruleDefinition, $activeTaskSession);

            return;
        }

        $startedScheduleRun = ScheduleRun::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        if (! $startedScheduleRun) {
            return;
        }

        $this->createIdleScheduleViolationIfNeeded($student, $ruleDefinition, $startedScheduleRun);
    }

    public function evaluateStoppedTaskSession(
        Student $student,
        TaskSession $taskSession,
        CarbonInterface $endedAt,
        int $finalDurationSeconds,
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
    ): void {
        if (! $taskSession->started_at || ! $taskSession->planned_duration_minutes || $taskSession->planned_duration_minutes <= 0) {
            return;
        }

        $baseDurationSeconds = max(0, (int) ($taskSession->duration_seconds ?? 0));
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
            ->where('schedule_run_id', $scheduleRun->id)
            ->whereIn('status', ['paused', 'completed'])
            ->whereNotNull('ended_at')
            ->latest('ended_at')
            ->first(['ended_at']);

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

        $this->createViolationOnce(
            $student,
            $ruleDefinition,
            'observe-time:idle:run:'.$scheduleRun->id.':anchor:'.$anchorAt->toAtomString(),
            $violationAt,
            'Automatic violation for staying outside any task for more than 5 minutes after the schedule started.',
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

        $violation = Violation::query()->firstOrCreate(
            ['auto_generated_key' => $autoGeneratedKey],
            [
                'student_id' => $student->id,
                'rule_definition_id' => $ruleDefinition->id,
                'status' => 'open',
                'rule_title_snapshot' => $ruleDefinition->title,
                'penalty_units' => 0,
                'occurred_at' => $occurredAt,
                'notes' => $details,
                'reported_by_user_id' => null,
            ],
        );

        if ($violation->wasRecentlyCreated) {
            $this->speechAnnouncementService->queueViolation($violation);
        }
    }
}
