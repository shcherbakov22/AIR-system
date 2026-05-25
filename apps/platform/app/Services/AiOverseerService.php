<?php

namespace App\Services;

use App\Models\AiOverseerDecision;
use App\Models\AiOverseerMessage;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationResolution;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AiOverseerService
{
    private const PROMPT_VERSION = 'ai-overseer-v1';

    private const AUTOMATIC_VIOLATION_RULE_TITLES = [
        'Observe the time',
        'Skipped scheduled task',
        'Task completed too quickly',
        'Left camera view',
    ];

    /**
     * @param  array<string, mixed>  $input
     */
    public function decide(Student $student, User $requester, array $input): AiOverseerDecision
    {
        $conversation = $this->conversationThread($student, $requester, $input);
        $studentReason = (string) ($input['student_reason'] ?? '');

        $conversation->messages()->create([
            'user_id' => $requester->id,
            'sender' => 'student',
            'body' => $studentReason,
            'is_final_decision' => ($input['intent'] ?? 'decide') === 'decide',
        ]);

        if (($input['intent'] ?? 'decide') === 'chat') {
            return $this->replyToConversation($conversation->fresh(['messages']), $student, $requester, $input);
        }

        $context = $this->buildContext($student, $input, $conversation->fresh(['messages']));
        try {
            $modelResult = $this->callModel($context);
        } catch (Throwable $exception) {
            $modelResult = $this->fallbackDecision($context, $exception->getMessage());
        }
        $normalized = $this->normalizeModelResult($modelResult, (string) $input['request_type']);
        $threshold = (int) config('services.ai_overseer.confidence_threshold', 75);

        $status = $normalized['requires_mentor'] || $normalized['confidence'] < $threshold
            ? 'mentor_review'
            : ($this->isApprovalDecision($normalized['decision']) ? 'approved' : 'denied');

        return DB::transaction(function () use ($student, $requester, $input, $context, $modelResult, $normalized, $status, $conversation) {
            $decision = AiOverseerDecision::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $decision->update([
                'requested_by_user_id' => $requester->id,
                'task_session_id' => $context['target']['task_session']['id'] ?? $decision->task_session_id,
                'request_type' => $input['request_type'],
                'status' => $status,
                'decision' => $normalized['decision'],
                'confidence' => $normalized['confidence'],
                'student_reason' => $input['student_reason'] ?? null,
                'student_message' => $normalized['student_message'],
                'mentor_summary' => $normalized['mentor_summary'],
                'reason' => $normalized['reason'],
                'context_snapshot' => $context,
                'raw_response' => $modelResult,
                'model' => config('services.ai_overseer.model'),
                'prompt_version' => config('services.ai_overseer.prompt_version', self::PROMPT_VERSION),
                'decided_at' => now(),
            ]);

            $decision->messages()->create([
                'sender' => 'assistant',
                'body' => $normalized['student_message'],
                'is_final_decision' => true,
                'metadata' => [
                    'status' => $status,
                    'decision' => $normalized['decision'],
                    'confidence' => $normalized['confidence'],
                    'reason' => $normalized['reason'],
                ],
            ]);

            if ($status === 'approved' && $normalized['decision'] === 'allow_skip_task') {
                $this->applySkipApproval($decision, $requester);
            }

            if ($status === 'approved' && $normalized['decision'] === 'remove_violation') {
                $this->applyViolationRemovalIfAllowed($decision, $requester);
            }

            if ($decision->fresh()->status === 'mentor_review') {
                $this->notifyMentor($decision->fresh());
            }

            return $decision->fresh([
                'student.user',
                'requestedBy',
                'reviewedBy',
                'violation',
                'scheduleRunBlock',
                'mentorChatMessage',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function conversationThread(Student $student, User $requester, array $input): AiOverseerDecision
    {
        if (! empty($input['ai_overseer_decision_id'])) {
            return AiOverseerDecision::query()
                ->whereKey((int) $input['ai_overseer_decision_id'])
                ->where('student_id', $student->id)
                ->firstOrFail();
        }

        $context = $this->buildContext($student, $input);

        return AiOverseerDecision::create([
            'student_id' => $student->id,
            'requested_by_user_id' => $requester->id,
            'violation_id' => $context['target']['violation']['id'] ?? null,
            'task_session_id' => $context['target']['task_session']['id'] ?? null,
            'schedule_run_id' => $context['target']['schedule_run']['id'] ?? null,
            'schedule_run_block_id' => $context['target']['schedule_run_block']['id'] ?? null,
            'request_type' => $input['request_type'],
            'status' => 'conversation',
            'confidence' => 0,
            'student_reason' => $input['student_reason'] ?? null,
            'context_snapshot' => $context,
            'model' => config('services.ai_overseer.model'),
            'prompt_version' => config('services.ai_overseer.prompt_version', self::PROMPT_VERSION),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function replyToConversation(
        AiOverseerDecision $decision,
        Student $student,
        User $requester,
        array $input,
    ): AiOverseerDecision {
        $context = $this->buildContext($student, $input, $decision);

        try {
            $modelResult = $this->callConversationModel($context);
            $message = trim((string) Arr::get($modelResult, 'assistant_message', 'I need more information before making a decision.'));
        } catch (Throwable $exception) {
            $modelResult = $this->fallbackConversationReply($exception->getMessage());
            $message = (string) $modelResult['assistant_message'];
        }

        $decision->update([
            'student_reason' => $input['student_reason'] ?? $decision->student_reason,
            'student_message' => $message,
            'mentor_summary' => Arr::get($modelResult, 'mentor_summary', $decision->mentor_summary),
            'reason' => Arr::get($modelResult, 'reason', $decision->reason),
            'context_snapshot' => $context,
            'raw_response' => $modelResult,
            'model' => config('services.ai_overseer.model'),
        ]);

        $decision->messages()->create([
            'sender' => 'assistant',
            'body' => $message,
            'metadata' => $modelResult,
        ]);

        return $decision->fresh([
            'student.user',
            'requestedBy',
            'reviewedBy',
            'violation',
            'scheduleRunBlock',
            'mentorChatMessage',
            'messages',
        ]);
    }

    public function review(AiOverseerDecision $decision, User $reviewer, string $status, ?string $notes): AiOverseerDecision
    {
        return DB::transaction(function () use ($decision, $reviewer, $status, $notes) {
            $decision = AiOverseerDecision::query()
                ->whereKey($decision->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($decision->status !== 'mentor_review') {
                return $decision->fresh([
                    'student.user',
                    'requestedBy',
                    'reviewedBy',
                    'violation',
                    'scheduleRunBlock',
                    'mentorChatMessage',
                ]);
            }

            $decision->update([
                'status' => $status,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'mentor_summary' => $notes ?: $decision->mentor_summary,
            ]);

            if ($status === 'approved') {
                $this->applyMentorApproval($decision->fresh(), $reviewer);
            }

            return $decision->fresh([
                'student.user',
                'requestedBy',
                'reviewedBy',
                'violation',
                'scheduleRunBlock',
                'mentorChatMessage',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildContext(Student $student, array $input, ?AiOverseerDecision $conversation = null): array
    {
        $student->loadMissing('user');

        $activeScheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->whereIn('status', ['active', 'paused'])
            ->latest('started_at')
            ->first();

        $activeTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        $recentTaskSessions = TaskSession::query()
            ->where('student_id', $student->id)
            ->latest('started_at')
            ->limit(8)
            ->get();

        $openViolations = Violation::query()
            ->where('student_id', $student->id)
            ->where('status', 'open')
            ->latest('occurred_at')
            ->limit(8)
            ->get();

        $targetScheduleRunBlock = isset($input['schedule_run_block_id'])
            ? ScheduleRunBlock::query()
                ->whereKey((int) $input['schedule_run_block_id'])
                ->whereHas('scheduleRun', fn ($query) => $query->where('student_id', $student->id))
                ->firstOrFail()
            : null;
        $targetScheduleRunBlock ??= $conversation?->scheduleRunBlock;

        $targetViolation = isset($input['violation_id'])
            ? Violation::query()
                ->whereKey((int) $input['violation_id'])
                ->where('student_id', $student->id)
                ->firstOrFail()
            : null;
        $targetViolation ??= $conversation?->violation;

        return [
            'request' => [
                'type' => $input['request_type'],
                'intent' => $input['intent'] ?? 'decide',
                'student_reason' => $input['student_reason'] ?? null,
                'created_at' => now()->toAtomString(),
            ],
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'status' => $student->status,
                'notes' => $student->notes,
            ],
            'target' => [
                'schedule_run' => $targetScheduleRunBlock?->scheduleRun
                    ? $this->scheduleRunPayload($targetScheduleRunBlock->scheduleRun)
                    : ($activeScheduleRun ? $this->scheduleRunPayload($activeScheduleRun) : null),
                'schedule_run_block' => $targetScheduleRunBlock ? $this->scheduleRunBlockPayload($targetScheduleRunBlock) : null,
                'task_session' => $activeTaskSession ? $this->taskSessionPayload($activeTaskSession) : null,
                'violation' => $targetViolation ? $this->violationPayload($targetViolation) : null,
            ],
            'recent_task_sessions' => $recentTaskSessions->map(fn (TaskSession $session) => $this->taskSessionPayload($session))->all(),
            'open_violations' => $openViolations->map(fn (Violation $violation) => $this->violationPayload($violation))->all(),
            'conversation' => $conversation
                ? [
                    'id' => $conversation->id,
                    'status' => $conversation->status,
                    'messages' => $conversation->messages
                        ->map(fn (AiOverseerMessage $message) => [
                            'sender' => $message->sender,
                            'body' => $message->body,
                            'is_final_decision' => $message->is_final_decision,
                            'created_at' => $message->created_at?->toAtomString(),
                        ])
                        ->all(),
                ]
                : null,
            'policy' => [
                'allowed_decisions' => [
                    'allow_skip_task',
                    'deny_skip_task',
                    'remove_violation',
                    'keep_violation',
                    'request_more_info',
                    'escalate_to_mentor',
                ],
                'escalate_when_unsure' => true,
                'serious_or_conflicting_cases_require_mentor' => true,
                'low_confidence_threshold' => (int) config('services.ai_overseer.confidence_threshold', 75),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function callModel(array $context): array
    {
        if (! config('services.ai_overseer.enabled', true)) {
            return $this->fallbackDecision($context, 'AI overseer is disabled.');
        }

        $apiKey = (string) config('services.ai_overseer.api_key', '');

        if ($apiKey === '') {
            return $this->fallbackDecision($context, 'OpenRouter API key is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(45)
            ->post(rtrim((string) config('services.ai_overseer.base_url'), '/').'/chat/completions', [
                'model' => config('services.ai_overseer.model'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => implode("\n", [
                            'You are a school schedule and violation overseer.',
                            'Be fair and moderately lenient for low-risk automatic violations.',
                            'Accept plausible student explanations when the logs do not directly contradict them.',
                            'Do not require perfect evidence for harmless schedule/timing mistakes.',
                            'For Left camera view violations, briefly leaving to ask a mentor/parent for help, request a website unblock, handle a technical issue, or solve normal school logistics is a plausible explanation.',
                            'For plausible low-risk Left camera view explanations with no direct contradiction in logs, prefer remove_violation with sufficient confidence over requesting mentor corroboration.',
                            'Return only valid compact JSON.',
                            'Never invent facts. If evidence is weak or risk is high, escalate_to_mentor.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'instructions' => [
                                'Pick exactly one decision from the allowed list.',
                                'Use the conversation history when present.',
                                'confidence must be an integer 0-100.',
                                'requires_mentor must be true when confidence is below threshold or evidence conflicts.',
                                'student_message should be short and direct.',
                                'mentor_summary should explain the decision and evidence.',
                            ],
                            'required_schema' => [
                                'decision' => 'string',
                                'confidence' => 'integer 0-100',
                                'requires_mentor' => 'boolean',
                                'reason' => 'string',
                                'student_message' => 'string',
                                'mentor_summary' => 'string',
                            ],
                            'context' => $context,
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenRouter overseer request failed: '.$response->status().' '.$response->body());
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '{}');
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenRouter overseer returned invalid JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function callConversationModel(array $context): array
    {
        if (! config('services.ai_overseer.enabled', true)) {
            return $this->fallbackConversationReply('AI overseer is disabled.');
        }

        $apiKey = (string) config('services.ai_overseer.api_key', '');

        if ($apiKey === '') {
            return $this->fallbackConversationReply('OpenRouter API key is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(45)
            ->post(rtrim((string) config('services.ai_overseer.base_url'), '/').'/chat/completions', [
                'model' => config('services.ai_overseer.model'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => implode("\n", [
                            'You are a school schedule and violation overseer.',
                            'This is a conversation, not a final decision.',
                            'Explain reasoning, ask for missing facts, and tell the student what evidence matters.',
                            'For Left camera view violations, treat briefly leaving to ask a mentor/parent for help, request a website unblock, handle a technical issue, or solve normal school logistics as a plausible explanation when logs do not contradict it.',
                            'Do not tell the student that mentor corroboration is necessary for a plausible low-risk Left camera view explanation unless there is a direct contradiction or high-risk context.',
                            'Do not approve skips or remove violations unless the request intent is decide.',
                            'Return only valid compact JSON.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'instructions' => [
                                'Reply to the latest student message using the target and conversation history.',
                                'Do not choose a final decision.',
                                'assistant_message should be short, direct, and useful.',
                                'If the student wants action, tell them to use Ask for decision.',
                            ],
                            'required_schema' => [
                                'assistant_message' => 'string',
                                'reason' => 'string',
                                'mentor_summary' => 'string',
                            ],
                            'context' => $context,
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenRouter overseer chat request failed: '.$response->status().' '.$response->body());
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '{}');
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenRouter overseer chat returned invalid JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{decision: string, confidence: int, requires_mentor: bool, reason: string, student_message: string, mentor_summary: string}
     */
    private function normalizeModelResult(array $result, string $requestType): array
    {
        $allowed = [
            'allow_skip_task',
            'deny_skip_task',
            'remove_violation',
            'keep_violation',
            'request_more_info',
            'escalate_to_mentor',
        ];

        $decision = (string) Arr::get($result, 'decision', 'escalate_to_mentor');
        if (! in_array($decision, $allowed, true)) {
            $decision = 'escalate_to_mentor';
        }

        if ($requestType === 'skip_task' && ! in_array($decision, ['allow_skip_task', 'deny_skip_task', 'request_more_info', 'escalate_to_mentor'], true)) {
            $decision = 'escalate_to_mentor';
        }

        if ($requestType === 'remove_violation' && ! in_array($decision, ['remove_violation', 'keep_violation', 'request_more_info', 'escalate_to_mentor'], true)) {
            $decision = 'escalate_to_mentor';
        }

        $confidence = max(0, min(100, (int) Arr::get($result, 'confidence', 0)));
        $requiresMentor = (bool) Arr::get($result, 'requires_mentor', false);

        return [
            'decision' => $decision,
            'confidence' => $confidence,
            'requires_mentor' => $requiresMentor || in_array($decision, ['request_more_info', 'escalate_to_mentor'], true),
            'reason' => trim((string) Arr::get($result, 'reason', 'No reason was provided.')),
            'student_message' => trim((string) Arr::get($result, 'student_message', 'This needs mentor review.')),
            'mentor_summary' => trim((string) Arr::get($result, 'mentor_summary', 'AI overseer could not make a confident decision.')),
        ];
    }

    private function isApprovalDecision(string $decision): bool
    {
        return in_array($decision, ['allow_skip_task', 'remove_violation'], true);
    }

    private function applySkipApproval(AiOverseerDecision $decision, User $requester): void
    {
        if (! config('services.ai_overseer.auto_apply_skip', true) || ! $decision->schedule_run_block_id) {
            return;
        }

        $block = ScheduleRunBlock::query()
            ->whereKey($decision->schedule_run_block_id)
            ->lockForUpdate()
            ->first();

        if (! $block || ! in_array($block->status, ['pending', 'paused'], true)) {
            return;
        }

        $block->update([
            'status' => 'skipped',
            'completed_at' => now(),
        ]);

        $scheduleRun = $block->scheduleRun;

        if (
            $scheduleRun
            && $scheduleRun->status === 'active'
            && ! $scheduleRun->blocks()->whereNotIn('status', ['completed', 'skipped'])->exists()
        ) {
            $scheduleRun->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by_user_id' => $requester->id,
            ]);
        }

        $decision->update([
            'action_taken' => 'schedule_block_marked_skipped',
            'reviewed_by_user_id' => $requester->id,
            'reviewed_at' => now(),
        ]);
    }

    private function applyViolationRemovalIfAllowed(AiOverseerDecision $decision, User $requester): void
    {
        $violation = $decision->violation;

        if (! $violation || $violation->status !== 'open') {
            return;
        }

        if ($decision->confidence < 80 || ! in_array($violation->rule_title_snapshot, self::AUTOMATIC_VIOLATION_RULE_TITLES, true)) {
            $decision->update(['status' => 'mentor_review']);

            return;
        }

        $violation->update(['status' => 'waived']);
        app(AutomaticViolationDismissalService::class)->dismiss($violation, $requester->id);

        ViolationResolution::create([
            'violation_id' => $violation->id,
            'action' => 'waived',
            'notes' => 'AI overseer recommended removal: '.$decision->reason,
            'recorded_at' => now(),
            'created_by_user_id' => $requester->id,
        ]);

        $decision->update([
            'action_taken' => 'automatic_violation_waived',
            'reviewed_by_user_id' => $requester->id,
            'reviewed_at' => now(),
        ]);
    }

    private function applyMentorApproval(AiOverseerDecision $decision, User $reviewer): void
    {
        if ($decision->request_type === 'skip_task') {
            $this->applySkipApproval($decision, $reviewer);

            return;
        }

        if ($decision->request_type === 'remove_violation') {
            $violation = $decision->violation;

            if (! $violation || $violation->status !== 'open') {
                return;
            }

            $violation->update(['status' => 'waived']);
            app(AutomaticViolationDismissalService::class)->dismiss($violation, $reviewer->id);

            ViolationResolution::create([
                'violation_id' => $violation->id,
                'action' => 'waived',
                'notes' => 'Mentor approved AI overseer request: '.$decision->reason,
                'recorded_at' => now(),
                'created_by_user_id' => $reviewer->id,
            ]);

            $decision->update([
                'action_taken' => 'mentor_approved_violation_waived',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
        }
    }

    private function notifyMentor(AiOverseerDecision $decision): void
    {
        if ($decision->mentor_notified_at) {
            return;
        }

        $student = $decision->student;

        if (! $student) {
            return;
        }

        $decision->update([
            'mentor_notified_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function fallbackDecision(array $context, string $reason): array
    {
        return [
            'decision' => 'escalate_to_mentor',
            'confidence' => 0,
            'requires_mentor' => true,
            'reason' => $reason,
            'student_message' => 'I sent this to your mentor because the AI overseer is unavailable.',
            'mentor_summary' => 'AI overseer unavailable for request type '.$context['request']['type'].'.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackConversationReply(string $reason): array
    {
        return [
            'assistant_message' => 'I could not reach the AI overseer. Ask for a decision if this needs mentor review.',
            'reason' => $reason,
            'mentor_summary' => 'AI overseer chat unavailable.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleRunPayload(ScheduleRun $scheduleRun): array
    {
        return [
            'id' => $scheduleRun->id,
            'status' => $scheduleRun->status,
            'name' => $scheduleRun->schedule_name_snapshot,
            'started_at' => $scheduleRun->started_at?->toAtomString(),
            'completed_at' => $scheduleRun->completed_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleRunBlockPayload(ScheduleRunBlock $block): array
    {
        return [
            'id' => $block->id,
            'position' => $block->position,
            'status' => $block->status,
            'task_title' => $block->task_title_snapshot,
            'duration_minutes' => $block->duration_minutes_snapshot,
            'started_at' => $block->started_at?->toAtomString(),
            'completed_at' => $block->completed_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskSessionPayload(TaskSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'task_title' => $session->task_title_snapshot,
            'planned_duration_minutes' => $session->planned_duration_minutes,
            'duration_seconds' => $session->duration_seconds,
            'started_at' => $session->started_at?->toAtomString(),
            'ended_at' => $session->ended_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function violationPayload(Violation $violation): array
    {
        return [
            'id' => $violation->id,
            'status' => $violation->status,
            'rule_title' => $violation->rule_title_snapshot,
            'penalty_units' => $violation->penalty_units,
            'occurred_at' => $violation->occurred_at?->toAtomString(),
            'notes' => $violation->notes,
        ];
    }
}
