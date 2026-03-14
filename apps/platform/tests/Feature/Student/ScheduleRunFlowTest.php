<?php

namespace Tests\Feature\Student;

use App\Enums\ScheduleWeekday;
use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\ScheduleRun;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\RuleDefinition;
use App\Models\TaskTemplate;
use App\Models\TaskSession;
use App\Models\User;
use App\Models\Violation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleRunFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function createObserveTheTimeRule(User $admin): RuleDefinition
    {
        return RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
    }

    protected function createStudent(User $studentUser, string $displayName = 'Schedule Runner'): Student
    {
        return Student::create([
            'user_id' => $studentUser->id,
            'display_name' => $displayName,
            'status' => 'active',
            'notes' => null,
        ]);
    }

    protected function createTaskTemplate(
        User $creator,
        string $title,
        int $defaultDurationMinutes,
        string $summary,
        string $instructions,
    ): TaskTemplate {
        return TaskTemplate::create([
            'title' => $title,
            'summary' => $summary,
            'instructions' => $instructions,
            'default_duration_minutes' => $defaultDurationMinutes,
            'is_active' => true,
            'created_by_user_id' => $creator->id,
        ]);
    }

    protected function createScheduleTemplate(Student $student): ScheduleTemplate
    {
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $essayTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Essay Draft',
            40,
            'Draft the essay response.',
            'Write until the timer ends.',
        );
        $readingTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Reading Review',
            30,
            'Read and summarize the text.',
            'Take notes while you read.',
        );

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Tuesday Run',
            'weekday' => ScheduleWeekday::Tuesday,
            'notes' => 'Main weekday schedule.',
            'created_by_user_id' => $student->user_id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $essayTemplate->id,
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => $essayTemplate->default_duration_minutes,
            'notes' => 'Essay block.',
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $readingTemplate->id,
            'position' => 2,
            'start_time' => '09:45',
            'duration_minutes' => $readingTemplate->default_duration_minutes,
            'notes' => 'Reading block.',
        ]);

        return $scheduleTemplate->fresh('entries');
    }

    public function test_student_can_start_a_schedule_run_and_see_it_on_the_home_page(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Tuesday Run'));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $this->assertSame('active', $scheduleRun->status);
        $this->assertSame('Tuesday Run', $scheduleRun->schedule_name_snapshot);
        $this->assertCount(2, $scheduleRun->blocks);
        $this->assertSame(['pending', 'pending'], $scheduleRun->blocks->pluck('status')->all());

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun.schedule_name', 'Tuesday Run')
                ->where('activeScheduleRun.weekday_label', ScheduleWeekday::Tuesday->label())
                ->where('activeScheduleRun.completed_blocks', 0)
                ->where('activeScheduleRun.total_blocks', 2)
                ->where('activeScheduleRun.next_block.position', 1)
                ->where('activeScheduleRun.blocks.0.task.title', 'Essay Draft')
            );
    }

    public function test_student_can_start_any_pending_schedule_block_out_of_order(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $secondBlock,
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Reading Review'));

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $secondBlock->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading Review',
        ]);

        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $secondBlock->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $firstBlock->id,
            'status' => 'pending',
        ]);
    }

    public function test_stopping_the_last_schedule_block_completes_the_schedule_run(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $firstTaskSession = TaskSession::query()->where('student_id', $student->id)->where('status', 'active')->sole();

        Carbon::setTestNow('2026-03-08 09:40:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $firstTaskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Essay Draft'));

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $secondBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Reading Review'));

        $secondTaskSession = TaskSession::query()->where('student_id', $student->id)->where('status', 'active')->sole();

        Carbon::setTestNow('2026-03-08 10:10:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $secondTaskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Reading Review') && str_contains($message, 'Tuesday Run'));

        Carbon::setTestNow();

        $scheduleRun->refresh();
        $scheduleRun->load('blocks');

        $this->assertSame('completed', $scheduleRun->status);
        $this->assertNotNull($scheduleRun->completed_at);
        $this->assertSame($studentUser->id, $scheduleRun->completed_by_user_id);
        $this->assertSame(['completed', 'completed'], $scheduleRun->blocks->pluck('status')->all());

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun', null)
                ->where('activeTaskSession', null)
            );
    }

    public function test_student_can_finish_a_schedule_with_uncompleted_blocks(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_finish_student',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $taskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:20:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession));

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.complete', $scheduleRun))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Tuesday Run') && str_contains($message, 'finished'));

        $scheduleRun->refresh();
        $scheduleRun->load('blocks');

        $this->assertSame('completed', $scheduleRun->status);
        $this->assertNotNull($scheduleRun->completed_at);
        $this->assertSame($studentUser->id, $scheduleRun->completed_by_user_id);
        $this->assertSame('completed', $scheduleRun->blocks->firstWhere('position', 1)->status);
        $this->assertSame('pending', $scheduleRun->blocks->firstWhere('position', 2)->status);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun', null)
            );

        Carbon::setTestNow();
    }

    public function test_student_can_pause_a_running_schedule_block_for_an_own_timer_and_resume_it(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $breakTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Break Timer',
            15,
            'Handle an urgent interruption.',
            'Pause the schedule until the interruption is handled.',
        );

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        Carbon::setTestNow('2026-03-08 09:12:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Tuesday Run'));

        $scheduleRun->refresh();
        $scheduleRun->load('blocks');

        $this->assertSame('paused', $scheduleRun->status);
        $this->assertSame('paused', $scheduleRun->blocks->firstWhere('position', 1)->status);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $firstBlock->id,
            'status' => 'paused',
            'duration_seconds' => 720,
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Break Timer',
            'planned_duration_minutes' => 15,
            'duration_seconds' => 0,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun.status', 'paused')
                ->where('activeScheduleRun.paused_block.position', 1)
                ->where('activeTaskSession.source_type', 'ad_hoc')
                ->where('activeTaskSession.task_title', 'Break Timer')
            );

        $adHocTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:27:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.resume', $scheduleRun))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Essay Draft'));

        $scheduleRun->refresh();
        $scheduleRun->load('blocks');

        $this->assertSame('active', $scheduleRun->status);
        $this->assertSame('in_progress', $scheduleRun->blocks->firstWhere('position', 1)->status);

        $this->assertDatabaseHas('task_sessions', [
            'id' => $adHocTaskSession->id,
            'student_id' => $student->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Break Timer',
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $firstBlock->id,
            'status' => 'active',
            'task_title_snapshot' => 'Essay Draft',
            'planned_duration_minutes' => 40,
            'duration_seconds' => 720,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeTaskSession.task_title', 'Essay Draft')
                ->where('activeTaskSession.duration_seconds', 720)
                ->where('activeTaskSession.planned_duration_minutes', 40)
            );

        Carbon::setTestNow('2026-03-08 09:55:00');

        $resumedTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('schedule_run_id', $scheduleRun->id)
            ->where('schedule_run_block_id', $firstBlock->id)
            ->where('status', 'active')
            ->latest('id')
            ->sole();

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $resumedTaskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Essay Draft'));

        $this->assertDatabaseHas('task_sessions', [
            'id' => $resumedTaskSession->id,
            'status' => 'completed',
            'duration_seconds' => 2400,
        ]);

        Carbon::setTestNow();
    }

    public function test_student_can_not_start_a_schedule_run_while_an_open_violation_exists(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $ruleDefinition = RuleDefinition::create([
            'title' => 'Follow the schedule',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 50,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => $ruleDefinition->title,
            'penalty_units' => 50,
            'occurred_at' => '2026-03-08 08:55:00',
            'notes' => 'Open violation blocks schedule progress.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Follow the schedule') && str_contains($message, '08:55'));

        $this->assertDatabaseCount('schedule_runs', 0);
    }

    public function test_student_can_not_start_a_schedule_run_while_unread_mentor_chat_exists_until_chat_is_opened(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_messages',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        ChatMessage::create([
            'student_id' => $student->id,
            'sender_user_id' => $mentor->id,
            'channel' => 'chat',
            'body' => 'Read this before starting.',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'unread mentor message'));

        $this->assertDatabaseCount('schedule_runs', 0);

        $this->actingAs($studentUser)
            ->get(route('student.chat.show'))
            ->assertOk();

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Tuesday Run'));
    }

    public function test_student_can_not_start_a_schedule_block_while_unread_announcements_exist_until_announcements_are_opened(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_announcements',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        ChatMessage::create([
            'student_id' => null,
            'sender_user_id' => $mentor->id,
            'channel' => 'announcement',
            'body' => 'Read the announcement before continuing.',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'unread announcement'));

        $this->assertDatabaseCount('task_sessions', 0);

        $this->actingAs($studentUser)
            ->get(route('student.announcements.show'))
            ->assertOk();

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $firstBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Essay Draft'));
    }

    public function test_student_can_not_pause_for_an_own_timer_while_an_open_violation_exists(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);
        $breakTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Break Timer',
            15,
            'Handle an urgent interruption.',
            'Pause the schedule until the interruption is handled.',
        );

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Act as planned',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 50,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => $ruleDefinition->title,
            'penalty_units' => 50,
            'occurred_at' => '2026-03-08 09:12:00',
            'notes' => 'Open violation blocks pause.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Act as planned') && str_contains($message, '09:12'));

        Carbon::setTestNow();

        $scheduleRun->refresh();
        $this->assertSame('active', $scheduleRun->status);
        $this->assertDatabaseMissing('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
        ]);
    }

    public function test_idle_schedule_gap_creates_one_automatic_observe_the_time_violation_and_blocks_starting_a_block(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($mentor);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        Carbon::setTestNow('2026-03-08 09:06:00');

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time'));

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'status' => 'open',
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $firstBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time'));

        $this->assertDatabaseCount('violations', 1);

        Carbon::setTestNow();
    }

    public function test_completed_schedule_does_not_create_idle_observe_the_time_violation(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($mentor);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        Carbon::setTestNow('2026-03-08 09:40:00');

        $firstTaskSession = TaskSession::query()->where('student_id', $student->id)->where('status', 'active')->sole();

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $firstTaskSession));

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $secondBlock->fresh(),
            ]));

        Carbon::setTestNow('2026-03-08 10:10:00');

        $secondTaskSession = TaskSession::query()->where('student_id', $student->id)->where('status', 'active')->sole();

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $secondTaskSession));

        Carbon::setTestNow('2026-03-08 10:20:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 0);

        Carbon::setTestNow();
    }
    public function test_idle_schedule_gap_creates_observe_the_time_violation_once_and_blocks_starting_the_next_block(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_observe_time',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($admin);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $firstTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:40:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $firstTaskSession));

        Carbon::setTestNow('2026-03-08 09:46:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $secondBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time') && str_contains($message, '09:45'));

        $violation = Violation::query()->where('student_id', $student->id)->sole();

        $this->assertSame('Observe the time', $violation->rule_title_snapshot);
        $this->assertSame('open', $violation->status);
        $this->assertSame(0, $violation->penalty_units);
        $this->assertSame(
            'observe-time:idle:run:'.$scheduleRun->id.':anchor:2026-03-08T09:40:00+00:00',
            $violation->auto_generated_key,
        );
        $this->assertSame('2026-03-08T09:45:00+00:00', $violation->occurred_at?->toAtomString());

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $secondBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time') && str_contains($message, '09:45'));

        $this->assertDatabaseCount('violations', 1);

        Carbon::setTestNow();
    }

    public function test_overdue_schedule_task_creates_observe_the_time_violation_once_and_blocks_pause_for_own_timer(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_observe_time',
        ]);
        $breakTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Break Timer',
            15,
            'Handle an urgent interruption.',
            'Pause the schedule until the interruption is handled.',
        );

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($admin);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        Carbon::setTestNow('2026-03-08 09:46:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time') && str_contains($message, '09:45'));

        $activeTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();
        $violation = Violation::query()->where('student_id', $student->id)->sole();

        $this->assertSame('Observe the time', $violation->rule_title_snapshot);
        $this->assertSame('open', $violation->status);
        $this->assertSame(0, $violation->penalty_units);
        $this->assertSame(
            'observe-time:overtime:session:'.$activeTaskSession->id.':threshold:2026-03-08T09:45:00+00:00',
            $violation->auto_generated_key,
        );
        $this->assertSame('2026-03-08T09:45:00+00:00', $violation->occurred_at?->toAtomString());

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time') && str_contains($message, '09:45'));

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseMissing('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
        ]);

        Carbon::setTestNow();
    }
}
