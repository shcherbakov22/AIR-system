<?php

namespace App\Services;

use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\Violation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AutomaticObserveTheTimeViolationService
{
    private const OBSERVE_TIME_RULE_TITLE = 'Observe the time';

    private const TOO_SHORT_TASK_RULE_TITLE = 'Task completed too quickly';

    private const SKIPPED_TASK_RULE_TITLE = 'Skipped scheduled task';

    private const GRACE_MINUTES = 5;

    private const MAX_CREATION_DELAY_MINUTES = 2;

    private const AUTO_KEY_PREFIX = 'observe-time:';

    private const MIN_SHORT_TASK_PLANNED_SECONDS = 600;

    private const MAX_SHORT_TASK_PLANNED_SECONDS = 5400;

    private const MIN_SHORT_TASK_MISSING_SECONDS = 300;

    private const SHORT_TASK_RATIO = 0.35;

    public function __construct(
        private readonly SpeechAnnouncementService $speechAnnouncementService,
        private readonly StudentPushUpCounterService $pushUpCounterService,
        private readonly TaskSessionSleepService $taskSessionSleepService,
        private readonly TaskSessionUnfinishService $taskSessionUnfinishService,
    ) {}

    public function evaluate(Student $student): void
    {
        $ruleDefinition = $this->observeTheTimeRule();

        if (! $ruleDefinition) {
            return;
        }

        $startedScheduleRun = ScheduleRun::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['active', 'paused'])
            ->latest('started_at')
            ->first();

        if ($startedScheduleRun && $this->skippedScheduleViolationsEnabled()) {
            if ($this->createSkippedScheduleBlockViolationIfNeeded($student, $ruleDefinition, $startedScheduleRun)) {
                return;
            }
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

        $this->createTooShortTaskViolationIfNeeded(
            $student,
            $ruleDefinition,
            $taskSession,
            max(0, $finalDurationSeconds),
            $endedAt,
        );
    }

    public function evaluateCompletedScheduleRun(
        Student $student,
        ScheduleRun $scheduleRun,
        CarbonInterface $completedAt,
    ): void {
        // Skipped schedule violations are evaluated only while the schedule is still active.
    }

    private function createSkippedScheduleBlockViolationIfNeeded(
        Student $student,
        RuleDefinition $ruleDefinition,
        ScheduleRun $scheduleRun,
    ): bool {
        if ($scheduleRun->status !== 'active') {
            return false;
        }

        $scheduleRun->loadMissing('blocks');
        $skippedTaskRuleDefinition = $this->automaticRule(self::SKIPPED_TASK_RULE_TITLE, $ruleDefinition);

        if ($this->hasOpenViolationForRule($student, $skippedTaskRuleDefinition)) {
            return true;
        }

        if ($this->hasSkippedScheduleViolationRecordForRun($scheduleRun)) {
            return true;
        }

        foreach ($scheduleRun->blocks->sortBy('position') as $block) {
            if ($block->status !== 'pending') {
                continue;
            }

            $violationAt = $this->scheduledBlockViolationAt($scheduleRun, $block);

            if (! $violationAt || now()->lt($violationAt)) {
                continue;
            }

            $violation = $this->createViolationOnce(
                $student,
                $skippedTaskRuleDefinition,
                'observe-time:skipped-block:run:'.$scheduleRun->id.':block:'.$block->id,
                $violationAt,
                'Automatic violation for missing a scheduled block while the schedule was still open: '.$block->task_title_snapshot.'.',
            );

            if ($violation) {
                return true;
            }
        }

        return false;
    }

    private function scheduledBlockViolationAt(ScheduleRun $scheduleRun, ScheduleRunBlock $block): ?CarbonInterface
    {
        if (! $scheduleRun->started_at || ! is_string($block->start_time_snapshot) || ! preg_match('/^\d{2}:\d{2}$/', $block->start_time_snapshot)) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $block->start_time_snapshot));

        return $scheduleRun->started_at
            ->copy()
            ->startOfDay()
            ->setTime($hour, $minute)
            ->addMinutes(max(0, (int) $block->duration_minutes_snapshot))
            ->addMinutes(self::GRACE_MINUTES);
    }

    private function skippedScheduleViolationsEnabled(): bool
    {
        $enabledAt = config('services.automatic_violations.skipped_schedule_enabled_at');

        if (! is_string($enabledAt) || trim($enabledAt) === '') {
            return false;
        }

        try {
            return now()->greaterThanOrEqualTo(Carbon::parse($enabledAt));
        } catch (\Throwable) {
            return false;
        }
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
        return $this->automaticRule(self::OBSERVE_TIME_RULE_TITLE);
    }

    private function automaticRule(string $title, ?RuleDefinition $fallbackRuleDefinition = null): ?RuleDefinition
    {
        return RuleDefinition::query()
            ->where('title', $title)
            ->where('scope', 'global')
            ->whereNull('student_id')
            ->where('is_active', true)
            ->first()
            ?? $fallbackRuleDefinition;
    }

    private function createOverdueTaskViolationIfNeeded(
        Student $student,
        RuleDefinition $ruleDefinition,
        TaskSession $taskSession,
    ): void {
        if ($this->hasOpenViolationForRule($student, $ruleDefinition)) {
            return;
        }

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
            $this->overtimeAutoGeneratedKey($taskSession, $violationAt),
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
        if ($this->hasOpenViolationForRule($student, $ruleDefinition)) {
            return;
        }

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
            $this->overtimeAutoGeneratedKey($taskSession, $violationAt),
            $violationAt,
            'Automatic violation for exceeding the planned task duration by more than 5 minutes.',
        );
    }

    private function overtimeAutoGeneratedKey(TaskSession $taskSession, CarbonInterface $violationAt): string
    {
        if ($taskSession->schedule_run_block_id) {
            return 'observe-time:overtime:block:'.$taskSession->schedule_run_block_id;
        }

        return 'observe-time:overtime:session:'.$taskSession->id.':threshold:'.$violationAt->toAtomString();
    }

    private function createTooShortTaskViolationIfNeeded(
        Student $student,
        RuleDefinition $ruleDefinition,
        TaskSession $taskSession,
        int $finalDurationSeconds,
        CarbonInterface $endedAt,
    ): void {
        if ($this->taskSessionSleepService->isSleepingSession($taskSession)) {
            return;
        }

        if ($taskSession->resumed_from_task_session_id !== null) {
            return;
        }

        $taskSession->loadMissing('taskTemplate');

        if ($taskSession->taskTemplate?->can_end_early) {
            return;
        }

        if (! $taskSession->planned_duration_minutes || $taskSession->planned_duration_minutes <= 0) {
            return;
        }

        if ($this->isTooShortExemptTask($taskSession)) {
            return;
        }

        $plannedSeconds = $taskSession->planned_duration_minutes * 60;

        if ($plannedSeconds < self::MIN_SHORT_TASK_PLANNED_SECONDS) {
            return;
        }

        if ($plannedSeconds > self::MAX_SHORT_TASK_PLANNED_SECONDS) {
            return;
        }

        if (($plannedSeconds - $finalDurationSeconds) < self::MIN_SHORT_TASK_MISSING_SECONDS) {
            return;
        }

        if ($finalDurationSeconds >= (int) floor($plannedSeconds * self::SHORT_TASK_RATIO)) {
            return;
        }

        $tooShortTaskRuleDefinition = $this->automaticRule(self::TOO_SHORT_TASK_RULE_TITLE, $ruleDefinition);

        $this->createViolationOnce(
            $student,
            $tooShortTaskRuleDefinition,
            'observe-time:too-short:session:'.$taskSession->id,
            $endedAt,
            'Automatic violation for completing a task too quickly to be credible: '.$taskSession->task_title_snapshot
                .' finished in '.(int) floor($finalDurationSeconds / 60).' minutes for a planned '
                .$taskSession->planned_duration_minutes.' minute task.',
        );
    }

    private function isTooShortExemptTask(TaskSession $taskSession): bool
    {
        $title = strtolower((string) $taskSession->task_title_snapshot);

        return str_contains($title, 'eating')
            || str_contains($title, 'cooking');
    }

    private function createIdleScheduleViolationIfNeeded(
        Student $student,
        RuleDefinition $ruleDefinition,
        ScheduleRun $scheduleRun,
    ): void {
        if ($this->hasOpenViolationForRule($student, $ruleDefinition)) {
            return;
        }

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
        if ($this->hasOpenViolationForRule($student, $ruleDefinition)) {
            return;
        }

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
    ): ?Violation {
        $wasDismissed = DB::table('dismissed_automatic_violations')
            ->where('auto_generated_key', $autoGeneratedKey)
            ->exists();

        if ($wasDismissed) {
            return null;
        }

        $violation = DB::transaction(function () use ($student, $ruleDefinition, $autoGeneratedKey, $occurredAt, $details) {
            Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->first();

            $existingViolation = Violation::query()
                ->where('auto_generated_key', $autoGeneratedKey)
                ->lockForUpdate()
                ->first();

            if ($existingViolation) {
                return $existingViolation;
            }

            if ($this->hasBlockingOpenViolation($student, $ruleDefinition)) {
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
            return null;
        }

        if ($violation->wasRecentlyCreated) {
            if (str_starts_with($autoGeneratedKey, self::AUTO_KEY_PREFIX.'overtime:')) {
                $this->taskSessionUnfinishService->completeActiveTaskForStudent($student, null);
            } else {
                $this->taskSessionUnfinishService->interruptActiveTaskForStudent($student, null);
            }
            $this->speechAnnouncementService->queueViolation($violation);
        }

        return $violation;
    }

    private function hasBlockingOpenViolation(Student $student, RuleDefinition $ruleDefinition): bool
    {
        $query = Violation::query()
            ->where('student_id', $student->id)
            ->where('status', 'open');

        if ($ruleDefinition->title === self::OBSERVE_TIME_RULE_TITLE) {
            $query->where('rule_title_snapshot', '!=', self::OBSERVE_TIME_RULE_TITLE);
        } else {
            $query->where(function ($query) {
                $query->whereNull('auto_generated_key')
                    ->orWhere('auto_generated_key', 'not like', self::AUTO_KEY_PREFIX.'%');
            });
        }

        return $query
            ->lockForUpdate()
            ->exists();
    }

    private function hasOpenViolationForRule(Student $student, RuleDefinition $ruleDefinition): bool
    {
        return Violation::query()
            ->where('student_id', $student->id)
            ->where('status', 'open')
            ->where('rule_title_snapshot', $ruleDefinition->title)
            ->exists();
    }

    private function hasSkippedScheduleViolationRecordForRun(ScheduleRun $scheduleRun): bool
    {
        $pattern = 'observe-time:skipped-block:run:'.$scheduleRun->id.':block:%';

        return Violation::query()
            ->where('auto_generated_key', 'like', $pattern)
            ->exists()
            || DB::table('dismissed_automatic_violations')
                ->where('auto_generated_key', 'like', $pattern)
                ->exists();
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
