<?php

namespace App\Services;

use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\StudentSetting;
use App\Models\TaskSession;
use App\Models\Violation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AttentionTrackingViolationService
{
    private const RULE_TITLE = 'Look away';
    private const BODY_MISSING_RULE_TITLE = 'Left camera view';
    private const AUTO_KEY_PREFIX = 'look-away:';
    private const BODY_MISSING_AUTO_KEY_PREFIX = 'body-missing:';
    private const ATTENTION_TRACKED_TASK_WORDS = [
        'coding',
        'crypto',
        'docs',
        'math',
        'history',
        'physics',
        'test',
        'tests',
    ];
    private const ATTENTION_TRACKED_TASK_PHRASES = [
        'bl typing',
    ];

    public function __construct(
        private readonly StudentPushUpCounterService $studentPushUpCounterService,
        private readonly TaskSessionUnfinishService $taskSessionUnfinishService,
        private readonly SpeechAnnouncementService $speechAnnouncementService,
        private readonly StudentAttentionStateService $studentAttentionStateService,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    public function recordLookAwayEvent(
        StudentDevice $device,
        ?CarbonInterface $occurredAt = null,
        array $payload = [],
        string $eventType = 'look_away',
    ): array {
        $occurredAt ??= now();

        return DB::transaction(function () use ($device, $occurredAt, $payload, $eventType) {
            $lockedDevice = StudentDevice::query()
                ->with('student')
                ->whereKey($device->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->recordAttentionEventForLockedStudent($lockedDevice->student, $eventType, $occurredAt, $payload);
        });
    }

    public function recordLookAwayEventForStudent(
        Student $student,
        ?CarbonInterface $occurredAt = null,
        array $payload = [],
        string $eventType = 'look_away',
    ): array {
        $occurredAt ??= now();

        return DB::transaction(function () use ($student, $occurredAt, $payload, $eventType) {
            $lockedStudent = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->recordAttentionEventForLockedStudent($lockedStudent, $eventType, $occurredAt, $payload);
        });
    }

    public function resetLookAwayCountForStudent(Student $student): void
    {
        $this->studentAttentionStateService->resetLookAwayCountForStudent($student);
    }

    private function lockSetting(Student $student): StudentSetting
    {
        $this->studentAttentionStateService->ensureSetting($student);

        return StudentSetting::query()
            ->where('student_id', $student->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lookAwayRule(): ?RuleDefinition
    {
        return RuleDefinition::query()
            ->where('title', self::RULE_TITLE)
            ->where('scope', 'global')
            ->whereNull('student_id')
            ->where('is_active', true)
            ->first();
    }

    private function bodyMissingRule(): ?RuleDefinition
    {
        return RuleDefinition::query()
            ->where('title', self::BODY_MISSING_RULE_TITLE)
            ->where('scope', 'global')
            ->whereNull('student_id')
            ->where('is_active', true)
            ->first() ?? $this->lookAwayRule();
    }

    private function recordAttentionEventForLockedStudent(
        Student $student,
        string $eventType,
        CarbonInterface $occurredAt,
        array $payload,
    ): array {
        if ($eventType === 'body_missing') {
            return $this->recordBodyMissingForLockedStudent($student, $occurredAt, $payload);
        }

        return $this->recordLookAwayForLockedStudent($student, $occurredAt, $payload);
    }

    private function recordLookAwayForLockedStudent(Student $student, CarbonInterface $occurredAt, array $payload): array
    {
        $activeTaskSession = $this->lockedActiveTaskSessionForStudent($student);

        if (! $activeTaskSession) {
            $this->studentAttentionStateService->resetLookAwayCountForStudent($student);

            return [
                'accepted' => true,
                'triggered_violation' => false,
                'count' => 0,
                'threshold' => $this->studentAttentionStateService->ensureSetting($student)->look_away_event_threshold,
                'reason' => 'no_active_task',
            ];
        }

        $this->activityLogService->log(
            'attention',
            'look_away_event',
            'Looked away during task: '.$activeTaskSession->task_title_snapshot,
            $student->id,
            null,
            $activeTaskSession,
            [
                'task_session_id' => $activeTaskSession->id,
                'task_title' => $activeTaskSession->task_title_snapshot,
                'reason' => $payload['reason'] ?? null,
                'score' => $payload['score'] ?? null,
                'away_seconds' => $payload['away_seconds'] ?? null,
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );

        $this->studentAttentionStateService->resetLookAwayCountForStudent($student);

        return [
            'accepted' => true,
            'triggered_violation' => false,
            'count' => 0,
            'threshold' => $this->studentAttentionStateService->ensureSetting($student)->look_away_event_threshold,
            'reason' => 'look_away_recorded',
        ];
    }

    private function recordBodyMissingForLockedStudent(Student $student, CarbonInterface $occurredAt, array $payload): array
    {
        $activeTaskSession = $this->lockedActiveTaskSessionForStudent($student);

        if (! $activeTaskSession) {
            $this->studentAttentionStateService->resetLookAwayCountForStudent($student);

            return [
                'accepted' => true,
                'triggered_violation' => false,
                'threshold_seconds' => $this->bodyMissingViolationSeconds(),
                'reason' => 'no_active_task',
            ];
        }

        if (! $this->taskSessionRequiresAttentionViolation($activeTaskSession)) {
            $this->studentAttentionStateService->resetLookAwayCountForStudent($student);

            return [
                'accepted' => true,
                'triggered_violation' => false,
                'threshold_seconds' => $this->bodyMissingViolationSeconds(),
                'reason' => 'task_not_attention_tracked',
            ];
        }

        $awaySeconds = (float) ($payload['away_seconds'] ?? 0);
        $thresholdSeconds = $this->bodyMissingViolationSeconds();

        if ($awaySeconds < $thresholdSeconds) {
            return [
                'accepted' => true,
                'triggered_violation' => false,
                'away_seconds' => $awaySeconds,
                'threshold_seconds' => $thresholdSeconds,
                'reason' => 'threshold_not_reached',
            ];
        }

        $autoGeneratedKey = self::BODY_MISSING_AUTO_KEY_PREFIX.'task-session:'.$activeTaskSession->id;
        $existingViolation = Violation::query()
            ->where('auto_generated_key', $autoGeneratedKey)
            ->lockForUpdate()
            ->first();

        if ($existingViolation) {
            return [
                'accepted' => true,
                'triggered_violation' => false,
                'away_seconds' => $awaySeconds,
                'threshold_seconds' => $thresholdSeconds,
                'reason' => 'violation_already_open',
            ];
        }

        $ruleDefinition = $this->bodyMissingRule();

        if (! $ruleDefinition) {
            return [
                'accepted' => true,
                'triggered_violation' => false,
                'away_seconds' => $awaySeconds,
                'threshold_seconds' => $thresholdSeconds,
                'reason' => 'missing_rule_definition',
            ];
        }

        $violation = $this->createViolation(
            $student,
            $activeTaskSession,
            $ruleDefinition,
            $autoGeneratedKey,
            $occurredAt,
            collect([
                'Automatic violation after the student left the camera view during the active task.',
                'Away seconds: '.(string) round($awaySeconds, 1),
                isset($payload['reason']) ? 'Reason: '.(string) $payload['reason'] : null,
                isset($payload['score']) ? 'Score: '.(string) $payload['score'] : null,
                isset($payload['body_confidence']) ? 'Body confidence: '.(string) $payload['body_confidence'] : null,
                'Task session: '.$activeTaskSession->task_title_snapshot,
            ])->filter()->implode(' '),
        );

        $this->studentAttentionStateService->resetLookAwayCountForStudent($student);

        return [
            'accepted' => true,
            'triggered_violation' => true,
            'away_seconds' => $awaySeconds,
            'threshold_seconds' => $thresholdSeconds,
            'violation_id' => $violation->id,
            'reason' => 'violation_created',
        ];
    }

    private function lockedActiveTaskSessionForStudent(Student $student): ?TaskSession
    {
        return TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->lockForUpdate()
            ->first();
    }

    private function createViolation(
        Student $student,
        TaskSession $activeTaskSession,
        RuleDefinition $ruleDefinition,
        string $autoGeneratedKey,
        CarbonInterface $occurredAt,
        string $notes,
    ): Violation {
        $pushUpCount = $this->studentPushUpCounterService->allocateForViolation($student);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => $ruleDefinition->title,
            'penalty_units' => $pushUpCount,
            'occurred_at' => $occurredAt,
            'auto_generated_key' => $autoGeneratedKey,
            'notes' => $notes,
            'reported_by_user_id' => null,
        ]);

        $this->taskSessionUnfinishService->interruptActiveTaskForStudent($student, null);
        $this->speechAnnouncementService->queueViolation($violation);

        return $violation;
    }

    private function bodyMissingViolationSeconds(): float
    {
        return max(1.0, (float) config('services.attention_tracking.body_missing_violation_seconds', 10));
    }

    private function taskSessionRequiresAttentionViolation(TaskSession $taskSession): bool
    {
        $titles = [
            (string) $taskSession->task_title_snapshot,
            (string) optional($taskSession->taskTemplate)->title,
        ];

        foreach ($titles as $title) {
            $normalizedTitle = trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($title)) ?? '');

            if (in_array($normalizedTitle, self::ATTENTION_TRACKED_TASK_PHRASES, true)) {
                return true;
            }

            $words = preg_split('/\s+/', $normalizedTitle, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (array_intersect($words, self::ATTENTION_TRACKED_TASK_WORDS) !== []) {
                return true;
            }
        }

        return false;
    }
}
