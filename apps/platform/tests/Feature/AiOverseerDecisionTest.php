<?php

namespace Tests\Feature;

use App\Enums\ScheduleWeekday;
use App\Enums\UserRole;
use App\Models\AiOverseerDecision;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\Violation;
use App\Services\AutomaticObserveTheTimeViolationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiOverseerDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(): Student
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ai_student_'.strtolower((string) str()->random(8)),
        ]);

        return Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'AI Student',
            'status' => 'active',
            'notes' => null,
        ]);
    }

    private function createScheduleBlock(Student $student): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $taskTemplate = TaskTemplate::create([
            'created_by_user_id' => $admin->id,
            'title' => 'Reading',
            'summary' => 'Read the assigned text.',
            'instructions' => 'Read carefully.',
            'default_duration_minutes' => 30,
            'can_end_early' => true,
            'is_active' => true,
        ]);
        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Today',
            'weekday' => ScheduleWeekday::Tuesday,
            'is_active' => true,
            'notes' => null,
            'created_by_user_id' => $student->user_id,
        ]);
        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'schedule_template_id' => $scheduleTemplate->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Today',
            'schedule_weekday_snapshot' => 'Tuesday',
            'schedule_notes_snapshot' => null,
            'started_at' => now(),
            'started_by_user_id' => $student->user_id,
        ]);
        $block = $scheduleRun->blocks()->create([
            'schedule_entry_id' => null,
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'status' => 'pending',
            'start_time_snapshot' => '09:00',
            'duration_minutes_snapshot' => 30,
            'task_title_snapshot' => 'Reading',
            'task_summary_snapshot' => 'Read the assigned text.',
            'task_instructions_snapshot' => 'Read carefully.',
            'entry_notes_snapshot' => null,
        ]);

        return [$scheduleRun, $block];
    }

    private function createObserveTheTimeRule(User $admin): RuleDefinition
    {
        return $this->createAutomaticRule($admin, 'Observe the time');
    }

    private function createTaskCompletedTooQuicklyRule(User $admin): RuleDefinition
    {
        return $this->createAutomaticRule($admin, 'Task completed too quickly');
    }

    private function createSkippedScheduledTaskRule(User $admin): RuleDefinition
    {
        return $this->createAutomaticRule($admin, 'Skipped scheduled task');
    }

    private function createAutomaticRule(User $admin, string $title): RuleDefinition
    {
        return RuleDefinition::create([
            'title' => $title,
            'description' => 'Automatic rule for '.$title.'.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
    }

    private function createActiveTaskSession(Student $student, string $title, int $plannedDurationMinutes): TaskSession
    {
        return TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => null,
            'status' => 'active',
            'task_title_snapshot' => $title,
            'task_summary_snapshot' => null,
            'task_instructions_snapshot' => null,
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => $plannedDurationMinutes,
            'duration_seconds' => 0,
            'started_at' => now(),
            'started_by_user_id' => $student->user_id,
        ]);
    }

    public function test_student_can_ask_ai_to_skip_schedule_block_and_approved_decision_is_applied(): void
    {
        config([
            'services.ai_overseer.api_key' => 'test-key',
            'services.ai_overseer.auto_apply_skip' => true,
        ]);

        Http::fake([
            'https://openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'decision' => 'allow_skip_task',
                                'confidence' => 92,
                                'requires_mentor' => false,
                                'reason' => 'Student already completed equivalent reading today.',
                                'student_message' => 'Approved. You may skip this block.',
                                'mentor_summary' => 'Approved skip request based on stated equivalent work.',
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $student = $this->createStudent();
        [$scheduleRun, $block] = $this->createScheduleBlock($student);

        $this->actingAs($student->user)
            ->post(route('student.ai-overseer-decisions.store'), [
                'request_type' => 'skip_task',
                'schedule_run_block_id' => $block->id,
                'student_reason' => 'I already did this reading this morning.',
            ])
            ->assertRedirect(route('student.ai-overseer-decisions.index', absolute: false))
            ->assertSessionHas('success', 'Approved. You may skip this block.');

        $this->assertDatabaseHas('ai_overseer_decisions', [
            'student_id' => $student->id,
            'schedule_run_block_id' => $block->id,
            'request_type' => 'skip_task',
            'status' => 'approved',
            'decision' => 'allow_skip_task',
            'confidence' => 92,
            'action_taken' => 'schedule_block_marked_skipped',
        ]);
        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $block->id,
            'status' => 'skipped',
        ]);
        $this->assertDatabaseHas('schedule_runs', [
            'id' => $scheduleRun->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'auto_generated_key' => 'observe-time:skipped-block:run:'.$scheduleRun->id.':block:'.$block->id,
        ]);
    }

    public function test_low_confidence_violation_removal_is_escalated_to_mentor(): void
    {
        config(['services.ai_overseer.api_key' => 'test-key']);

        Http::fake([
            'https://openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'decision' => 'remove_violation',
                                'confidence' => 52,
                                'requires_mentor' => true,
                                'reason' => 'The student claim conflicts with the recorded task duration.',
                                'student_message' => 'I sent this to your mentor for review.',
                                'mentor_summary' => 'Student asked to remove a violation, but evidence is weak.',
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $rule = RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Follow schedule timing.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $rule->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Observe the time',
            'penalty_units' => 10,
            'occurred_at' => now(),
            'notes' => 'Task was completed too quickly.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.ai-overseer-decisions.store'), [
                'request_type' => 'remove_violation',
                'violation_id' => $violation->id,
                'student_reason' => 'I finished it correctly.',
            ])
            ->assertRedirect(route('student.ai-overseer-decisions.index', absolute: false))
            ->assertSessionHas('success', 'I sent this to your mentor for review.');

        $this->assertDatabaseHas('ai_overseer_decisions', [
            'student_id' => $student->id,
            'violation_id' => $violation->id,
            'request_type' => 'remove_violation',
            'status' => 'mentor_review',
            'decision' => 'remove_violation',
            'confidence' => 52,
        ]);
        $decision = AiOverseerDecision::query()->where('student_id', $student->id)->sole();
        $this->assertNull($decision->mentor_chat_message_id);
        $this->assertNotNull($decision->mentor_notified_at);
        $this->assertDatabaseMissing('chat_messages', [
            'student_id' => $student->id,
            'sender_user_id' => $student->user_id,
            'channel' => 'chat',
        ]);
        $this->assertDatabaseHas('violations', [
            'id' => $violation->id,
            'status' => 'open',
        ]);
    }

    public function test_approved_skip_marks_block_skipped_and_allows_next_block_to_start(): void
    {
        config(['services.ai_overseer.api_key' => 'test-key']);

        Http::fake([
            'https://openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'decision' => 'allow_skip_task',
                                'confidence' => 95,
                                'requires_mentor' => false,
                                'reason' => 'The student already completed equivalent work.',
                                'student_message' => 'Approved. You may skip this block.',
                                'mentor_summary' => 'Approved skip request.',
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $student = $this->createStudent();
        [$scheduleRun, $firstBlock] = $this->createScheduleBlock($student);
        $secondBlock = $scheduleRun->blocks()->create([
            'schedule_entry_id' => null,
            'task_template_id' => $firstBlock->task_template_id,
            'position' => 2,
            'status' => 'pending',
            'start_time_snapshot' => '09:30',
            'duration_minutes_snapshot' => 30,
            'task_title_snapshot' => 'Writing',
            'task_summary_snapshot' => 'Write notes.',
            'task_instructions_snapshot' => 'Write carefully.',
            'entry_notes_snapshot' => null,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.ai-overseer-decisions.store'), [
                'request_type' => 'skip_task',
                'schedule_run_block_id' => $firstBlock->id,
                'student_reason' => 'I already did this reading this morning.',
            ])
            ->assertRedirect(route('student.ai-overseer-decisions.index', absolute: false));

        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $firstBlock->id,
            'status' => 'skipped',
        ]);
        $this->assertDatabaseHas('schedule_runs', [
            'id' => $scheduleRun->id,
            'status' => 'active',
        ]);

        $this->actingAs($student->user)
            ->post(route('student.schedule-run-blocks.start', [$scheduleRun, $secondBlock]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Task session Writing started.');

        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $secondBlock->id,
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'auto_generated_key' => 'observe-time:skipped-block:run:'.$scheduleRun->id.':block:'.$firstBlock->id,
        ]);
    }

    public function test_student_can_chat_then_ask_for_final_ai_decision_with_history(): void
    {
        config(['services.ai_overseer.api_key' => 'test-key']);

        Http::fake([
            'https://openrouter.ai/*' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'assistant_message' => 'Tell me what equivalent work you already completed.',
                                    'reason' => 'More evidence is needed before a decision.',
                                    'mentor_summary' => 'Student is discussing a skip request.',
                                ]),
                            ],
                        ],
                    ],
                ])
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'decision' => 'allow_skip_task',
                                    'confidence' => 90,
                                    'requires_mentor' => false,
                                    'reason' => 'The student explained equivalent work in the chat.',
                                    'student_message' => 'Approved. You may skip this block.',
                                    'mentor_summary' => 'Approved after student provided follow-up details.',
                                ]),
                            ],
                        ],
                    ],
                ]),
        ]);

        $student = $this->createStudent();
        [, $block] = $this->createScheduleBlock($student);

        $this->actingAs($student->user)
            ->post(route('student.ai-overseer-decisions.store'), [
                'intent' => 'chat',
                'request_type' => 'skip_task',
                'schedule_run_block_id' => $block->id,
                'student_reason' => 'Can you explain what would make skipping okay?',
            ])
            ->assertRedirect(route('student.ai-overseer-decisions.index', [
                'ai_overseer_decision_id' => 1,
            ], absolute: false))
            ->assertSessionHas('success', 'Tell me what equivalent work you already completed.');

        $decision = AiOverseerDecision::query()->where('student_id', $student->id)->sole();

        $this->assertSame('conversation', $decision->status);
        $this->assertDatabaseHas('ai_overseer_messages', [
            'ai_overseer_decision_id' => $decision->id,
            'sender' => 'assistant',
            'body' => 'Tell me what equivalent work you already completed.',
            'is_final_decision' => false,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.ai-overseer-decisions.store'), [
                'intent' => 'decide',
                'ai_overseer_decision_id' => $decision->id,
                'request_type' => 'skip_task',
                'student_reason' => 'I already did the same reading with notes this morning.',
            ])
            ->assertRedirect(route('student.ai-overseer-decisions.index', [
                'ai_overseer_decision_id' => $decision->id,
            ], absolute: false))
            ->assertSessionHas('success', 'Approved. You may skip this block.');

        $this->assertDatabaseHas('ai_overseer_decisions', [
            'id' => $decision->id,
            'status' => 'approved',
            'decision' => 'allow_skip_task',
            'action_taken' => 'schedule_block_marked_skipped',
        ]);
        $this->assertDatabaseHas('ai_overseer_messages', [
            'ai_overseer_decision_id' => $decision->id,
            'sender' => 'assistant',
            'body' => 'Approved. You may skip this block.',
            'is_final_decision' => true,
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $content = $body['messages'][1]['content'] ?? '';

            return str_contains($content, 'Tell me what equivalent work you already completed.')
                && str_contains($content, 'I already did the same reading with notes this morning.');
        });
    }

    public function test_eighty_percent_removal_can_waive_typed_automatic_violation(): void
    {
        config(['services.ai_overseer.api_key' => 'test-key']);

        Http::fake([
            'https://openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'decision' => 'remove_violation',
                                'confidence' => 80,
                                'requires_mentor' => false,
                                'reason' => 'The planned duration was entered incorrectly.',
                                'student_message' => 'I removed that violation.',
                                'mentor_summary' => 'AI waived a typed automatic violation.',
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $rule = $this->createTaskCompletedTooQuicklyRule($admin);
        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $rule->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Task completed too quickly',
            'penalty_units' => 10,
            'occurred_at' => now(),
            'notes' => 'Task was completed too quickly.',
            'reported_by_user_id' => null,
            'auto_generated_key' => 'observe-time:too-short:session:999',
        ]);

        $this->actingAs($student->user)
            ->post(route('student.ai-overseer-decisions.store'), [
                'request_type' => 'remove_violation',
                'violation_id' => $violation->id,
                'student_reason' => 'The planned duration was wrong.',
            ])
            ->assertRedirect(route('student.ai-overseer-decisions.index', absolute: false))
            ->assertSessionHas('success', 'I removed that violation.');

        $this->assertDatabaseHas('violations', [
            'id' => $violation->id,
            'status' => 'waived',
        ]);
        $this->assertDatabaseHas('ai_overseer_decisions', [
            'student_id' => $student->id,
            'violation_id' => $violation->id,
            'request_type' => 'remove_violation',
            'status' => 'approved',
            'confidence' => 80,
            'action_taken' => 'automatic_violation_waived',
        ]);
    }

    public function test_admin_can_review_escalated_ai_overseer_decision(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $decision = AiOverseerDecision::create([
            'student_id' => $student->id,
            'requested_by_user_id' => $student->user_id,
            'request_type' => 'skip_task',
            'status' => 'mentor_review',
            'decision' => 'escalate_to_mentor',
            'confidence' => 0,
            'student_reason' => 'I need help.',
            'student_message' => 'Sent to mentor.',
            'mentor_summary' => 'Needs review.',
            'reason' => 'AI unavailable.',
            'context_snapshot' => [],
            'raw_response' => [],
            'model' => 'openai/gpt-oss-120b',
            'prompt_version' => 'ai-overseer-v1',
            'decided_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.ai-overseer-decisions.update', $decision), [
                'status' => 'denied',
                'notes' => 'Mentor reviewed and denied.',
            ])
            ->assertRedirect(route('admin.ai-overseer-decisions.index', absolute: false))
            ->assertSessionHas('success', 'AI overseer request updated.');

        $this->assertDatabaseHas('ai_overseer_decisions', [
            'id' => $decision->id,
            'status' => 'denied',
            'reviewed_by_user_id' => $admin->id,
            'mentor_summary' => 'Mentor reviewed and denied.',
        ]);
    }

    public function test_student_cannot_submit_argument_resolution_request(): void
    {
        Http::fake();

        $student = $this->createStudent();

        $this->actingAs($student->user)
            ->post(route('student.ai-overseer-decisions.store'), [
                'request_type' => 'resolve_argument',
                'student_reason' => 'Another student disagrees with me.',
            ])
            ->assertSessionHasErrors('request_type');

        $this->assertDatabaseCount('ai_overseer_decisions', 0);
    }

    public function test_ai_failure_falls_back_to_mentor_review_and_notifies_mentor(): void
    {
        config(['services.ai_overseer.api_key' => 'test-key']);

        Http::fake([
            'https://openrouter.ai/*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $student = $this->createStudent();
        [, $block] = $this->createScheduleBlock($student);

        $this->actingAs($student->user)
            ->post(route('student.ai-overseer-decisions.store'), [
                'request_type' => 'skip_task',
                'schedule_run_block_id' => $block->id,
                'student_reason' => 'I need mentor help.',
            ])
            ->assertRedirect(route('student.ai-overseer-decisions.index', absolute: false))
            ->assertSessionHas('success', 'I sent this to your mentor because the AI overseer is unavailable.');

        $decision = AiOverseerDecision::query()->where('student_id', $student->id)->sole();

        $this->assertSame('mentor_review', $decision->status);
        $this->assertSame('escalate_to_mentor', $decision->decision);
        $this->assertSame(0, $decision->confidence);
        $this->assertNull($decision->mentor_chat_message_id);
        $this->assertNotNull($decision->mentor_notified_at);
        $this->assertDatabaseMissing('chat_messages', [
            'student_id' => $student->id,
            'sender_user_id' => $student->user_id,
            'channel' => 'chat',
        ]);
    }

    public function test_student_cannot_request_ai_review_for_another_students_violation_or_block(): void
    {
        config(['services.ai_overseer.api_key' => 'test-key']);
        Http::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();
        [, $otherBlock] = $this->createScheduleBlock($otherStudent);
        $rule = $this->createObserveTheTimeRule($admin);
        $otherViolation = Violation::create([
            'student_id' => $otherStudent->id,
            'rule_definition_id' => $rule->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Observe the time',
            'penalty_units' => 10,
            'occurred_at' => now(),
            'notes' => 'Other student violation.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.ai-overseer-decisions.store'), [
                'request_type' => 'skip_task',
                'schedule_run_block_id' => $otherBlock->id,
                'student_reason' => 'This is not mine.',
            ])
            ->assertNotFound();

        $this->actingAs($student->user)
            ->post(route('student.ai-overseer-decisions.store'), [
                'request_type' => 'remove_violation',
                'violation_id' => $otherViolation->id,
                'student_reason' => 'This is not mine.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('ai_overseer_decisions', 0);
    }

    public function test_reviewed_decision_is_not_applied_twice_or_overwritten(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        [$scheduleRun, $block] = $this->createScheduleBlock($student);
        $decision = AiOverseerDecision::create([
            'student_id' => $student->id,
            'requested_by_user_id' => $student->user_id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $block->id,
            'request_type' => 'skip_task',
            'status' => 'mentor_review',
            'decision' => 'escalate_to_mentor',
            'confidence' => 0,
            'student_reason' => 'I need to skip this.',
            'student_message' => 'Sent to mentor.',
            'mentor_summary' => 'Needs review.',
            'reason' => 'AI unavailable.',
            'context_snapshot' => [],
            'raw_response' => [],
            'model' => 'openai/gpt-oss-120b',
            'prompt_version' => 'ai-overseer-v1',
            'decided_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.ai-overseer-decisions.update', $decision), [
                'status' => 'approved',
                'notes' => 'Approved once.',
            ])
            ->assertRedirect(route('admin.ai-overseer-decisions.index', absolute: false));

        $this->actingAs($admin)
            ->patch(route('admin.ai-overseer-decisions.update', $decision->fresh()), [
                'status' => 'denied',
                'notes' => 'Second review should not overwrite.',
            ])
            ->assertRedirect(route('admin.ai-overseer-decisions.index', absolute: false));

        $this->assertDatabaseHas('ai_overseer_decisions', [
            'id' => $decision->id,
            'status' => 'approved',
            'mentor_summary' => 'Approved once.',
            'action_taken' => 'schedule_block_marked_skipped',
        ]);
    }

    public function test_admin_approval_applies_escalated_skip_request(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        [$scheduleRun, $block] = $this->createScheduleBlock($student);
        $decision = AiOverseerDecision::create([
            'student_id' => $student->id,
            'requested_by_user_id' => $student->user_id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $block->id,
            'request_type' => 'skip_task',
            'status' => 'mentor_review',
            'decision' => 'escalate_to_mentor',
            'confidence' => 0,
            'student_reason' => 'I need to skip this.',
            'student_message' => 'Sent to mentor.',
            'mentor_summary' => 'Needs review.',
            'reason' => 'AI unavailable.',
            'context_snapshot' => [],
            'raw_response' => [],
            'model' => 'openai/gpt-oss-120b',
            'prompt_version' => 'ai-overseer-v1',
            'decided_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.ai-overseer-decisions.update', $decision), [
                'status' => 'approved',
                'notes' => 'Mentor approved the skip.',
            ])
            ->assertRedirect(route('admin.ai-overseer-decisions.index', absolute: false));

        $this->assertDatabaseHas('ai_overseer_decisions', [
            'id' => $decision->id,
            'status' => 'approved',
            'reviewed_by_user_id' => $admin->id,
            'mentor_summary' => 'Mentor approved the skip.',
            'action_taken' => 'schedule_block_marked_skipped',
        ]);
        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $block->id,
            'status' => 'skipped',
        ]);
    }

    public function test_admin_approval_applies_escalated_violation_removal_request(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $rule = RuleDefinition::create([
            'title' => 'Custom rule',
            'description' => 'Follow instructions.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $rule->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Custom rule',
            'penalty_units' => 10,
            'occurred_at' => now(),
            'notes' => 'Needs review.',
            'reported_by_user_id' => $admin->id,
        ]);
        $decision = AiOverseerDecision::create([
            'student_id' => $student->id,
            'requested_by_user_id' => $student->user_id,
            'violation_id' => $violation->id,
            'request_type' => 'remove_violation',
            'status' => 'mentor_review',
            'decision' => 'remove_violation',
            'confidence' => 40,
            'student_reason' => 'This violation is wrong.',
            'student_message' => 'Sent to mentor.',
            'mentor_summary' => 'Needs review.',
            'reason' => 'Evidence was unclear.',
            'context_snapshot' => [],
            'raw_response' => [],
            'model' => 'openai/gpt-oss-120b',
            'prompt_version' => 'ai-overseer-v1',
            'decided_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.ai-overseer-decisions.update', $decision), [
                'status' => 'approved',
                'notes' => 'Mentor approved the removal.',
            ])
            ->assertRedirect(route('admin.ai-overseer-decisions.index', absolute: false));

        $this->assertDatabaseHas('ai_overseer_decisions', [
            'id' => $decision->id,
            'status' => 'approved',
            'reviewed_by_user_id' => $admin->id,
            'mentor_summary' => 'Mentor approved the removal.',
            'action_taken' => 'mentor_approved_violation_waived',
        ]);
        $this->assertDatabaseHas('violations', [
            'id' => $violation->id,
            'status' => 'waived',
        ]);
    }

    public function test_task_completed_too_quickly_creates_reviewable_too_short_task_violation(): void
    {
        Carbon::setTestNow('2026-05-05 09:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $this->createObserveTheTimeRule($admin);
        $tooShortRule = $this->createTaskCompletedTooQuicklyRule($admin);
        [$scheduleRun, $block] = $this->createScheduleBlock($student);
        $block->taskTemplate?->update(['can_end_early' => false]);
        $block->update(['status' => 'in_progress']);
        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $block->id,
            'task_template_id' => $block->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'task_summary_snapshot' => 'Read the assigned text.',
            'task_instructions_snapshot' => 'Read carefully.',
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 30,
            'duration_seconds' => 0,
            'started_at' => now(),
            'started_by_user_id' => $student->user_id,
        ]);

        Carbon::setTestNow('2026-05-05 09:10:00');

        $taskSession->update([
            'status' => 'completed',
            'ended_at' => now(),
            'duration_seconds' => 600,
        ]);

        $this->app
            ->make(AutomaticObserveTheTimeViolationService::class)
            ->evaluateStoppedTaskSession($student, $taskSession->fresh('taskTemplate'), now(), 600, 0);

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $tooShortRule->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Task completed too quickly',
            'auto_generated_key' => 'observe-time:too-short:session:'.$taskSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_task_completed_at_thirty_five_percent_does_not_create_too_short_violation(): void
    {
        Carbon::setTestNow('2026-05-05 09:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $this->createObserveTheTimeRule($admin);
        $tooShortRule = $this->createTaskCompletedTooQuicklyRule($admin);
        [$scheduleRun, $block] = $this->createScheduleBlock($student);
        $block->update(['status' => 'in_progress']);
        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $block->id,
            'task_template_id' => $block->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'task_summary_snapshot' => 'Read the assigned text.',
            'task_instructions_snapshot' => 'Read carefully.',
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 30,
            'duration_seconds' => 0,
            'started_at' => now(),
            'started_by_user_id' => $student->user_id,
        ]);

        Carbon::setTestNow('2026-05-05 09:10:30');

        $this->actingAs($student->user)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $tooShortRule->id,
            'auto_generated_key' => 'observe-time:too-short:session:'.$taskSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_too_short_violation_keeps_minimum_missing_time_requirement(): void
    {
        Carbon::setTestNow('2026-05-05 09:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $this->createObserveTheTimeRule($admin);
        $tooShortRule = $this->createTaskCompletedTooQuicklyRule($admin);
        [$scheduleRun, $block] = $this->createScheduleBlock($student);
        $block->update([
            'status' => 'in_progress',
            'duration_minutes_snapshot' => 6,
        ]);
        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $block->id,
            'task_template_id' => $block->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Short Reading',
            'task_summary_snapshot' => 'Read the assigned text.',
            'task_instructions_snapshot' => 'Read carefully.',
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 6,
            'duration_seconds' => 0,
            'started_at' => now(),
            'started_by_user_id' => $student->user_id,
        ]);

        Carbon::setTestNow('2026-05-05 09:01:01');

        $this->actingAs($student->user)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $tooShortRule->id,
            'auto_generated_key' => 'observe-time:too-short:session:'.$taskSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_tasks_under_ten_minutes_are_exempt_from_too_short_violation(): void
    {
        Carbon::setTestNow('2026-05-05 09:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $this->createObserveTheTimeRule($admin);
        $tooShortRule = $this->createTaskCompletedTooQuicklyRule($admin);
        $taskSession = $this->createActiveTaskSession($student, 'Quick cleanup', 9);

        Carbon::setTestNow('2026-05-05 09:01:00');

        $this->actingAs($student->user)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $tooShortRule->id,
            'auto_generated_key' => 'observe-time:too-short:session:'.$taskSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_tasks_over_ninety_minutes_are_exempt_from_too_short_violation(): void
    {
        Carbon::setTestNow('2026-05-05 09:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $this->createObserveTheTimeRule($admin);
        $tooShortRule = $this->createTaskCompletedTooQuicklyRule($admin);
        $taskSession = $this->createActiveTaskSession($student, 'Long project block', 91);

        Carbon::setTestNow('2026-05-05 09:10:00');

        $this->actingAs($student->user)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $tooShortRule->id,
            'auto_generated_key' => 'observe-time:too-short:session:'.$taskSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_eating_and_cooking_tasks_are_exempt_from_too_short_violation(): void
    {
        Carbon::setTestNow('2026-05-05 09:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $this->createObserveTheTimeRule($admin);
        $tooShortRule = $this->createTaskCompletedTooQuicklyRule($admin);

        $eatingSession = $this->createActiveTaskSession($student, 'Eating lunch', 30);
        Carbon::setTestNow('2026-05-05 09:03:00');
        $this->actingAs($student->user)
            ->patch(route('student.task-sessions.stop', $eatingSession))
            ->assertRedirect(route('student.home', absolute: false));

        Carbon::setTestNow('2026-05-05 10:00:00');
        $cookingSession = $this->createActiveTaskSession($student, 'Cooking dinner', 45);
        Carbon::setTestNow('2026-05-05 10:05:00');
        $this->actingAs($student->user)
            ->patch(route('student.task-sessions.stop', $cookingSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $tooShortRule->id,
            'auto_generated_key' => 'observe-time:too-short:session:'.$eatingSession->id,
        ]);
        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $tooShortRule->id,
            'auto_generated_key' => 'observe-time:too-short:session:'.$cookingSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_tasks_marked_can_end_early_are_exempt_from_too_short_violation(): void
    {
        Carbon::setTestNow('2026-05-05 09:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $this->createObserveTheTimeRule($admin);
        $tooShortRule = $this->createTaskCompletedTooQuicklyRule($admin);

        $taskTemplate = TaskTemplate::create([
            'created_by_user_id' => $admin->id,
            'title' => 'Tennis',
            'summary' => null,
            'instructions' => null,
            'default_duration_minutes' => 55,
            'can_end_early' => true,
            'is_active' => true,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Tennis',
            'task_summary_snapshot' => null,
            'task_instructions_snapshot' => null,
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 55,
            'duration_seconds' => 0,
            'started_at' => now(),
            'started_by_user_id' => $student->user_id,
        ]);

        Carbon::setTestNow('2026-05-05 09:05:00');

        $this->actingAs($student->user)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $tooShortRule->id,
            'auto_generated_key' => 'observe-time:too-short:session:'.$taskSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_open_schedule_with_past_pending_block_does_not_create_skipped_task_violation_before_activation_time(): void
    {
        Carbon::setTestNow('2026-05-05 09:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = $this->createStudent();
        $this->createObserveTheTimeRule($admin);
        $this->createSkippedScheduledTaskRule($admin);
        [$scheduleRun, $block] = $this->createScheduleBlock($student);

        Carbon::setTestNow('2026-05-05 09:35:00');

        $this->actingAs($student->user)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseHas('schedule_runs', [
            'id' => $scheduleRun->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Skipped scheduled task',
            'auto_generated_key' => 'observe-time:skipped-block:run:'.$scheduleRun->id.':block:'.$block->id,
        ]);

        Carbon::setTestNow();
    }
}
