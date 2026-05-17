<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\EvaluateObserveTheTimeViolationsCommand;
use App\Enums\UserRole;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\Violation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EvaluateObserveTheTimeViolationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_overdue_task_observe_the_time_violation_without_page_load(): void
    {
        Carbon::setTestNow('2026-03-23 10:36:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'observe_command_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Observe Command Student',
            'status' => 'active',
            'notes' => null,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->artisan(EvaluateObserveTheTimeViolationsCommand::class)
            ->expectsOutput('Evaluated Observe the time for 1 students.')
            ->assertSuccessful();

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'status' => 'open',
        ]);

        Carbon::setTestNow();
    }

    public function test_command_marks_overtime_active_task_completed_when_violation_is_created(): void
    {
        Carbon::setTestNow('2026-03-23 10:36:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'observe_complete_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Observe Complete Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->artisan(EvaluateObserveTheTimeViolationsCommand::class)
            ->expectsOutput('Evaluated Observe the time for 1 students.')
            ->assertSuccessful();

        $taskSession->refresh();

        $this->assertSame('completed', $taskSession->status);
        $this->assertNotNull($taskSession->ended_at);
        $this->assertGreaterThanOrEqual(35 * 60, (int) $taskSession->duration_seconds);

        Carbon::setTestNow();
    }

    public function test_command_does_not_create_observe_the_time_violation_while_another_open_violation_exists(): void
    {
        Carbon::setTestNow('2026-03-23 10:36:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $blockingRule = RuleDefinition::create([
            'title' => 'Talking',
            'description' => 'No talking.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 25,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'observe_blocked_by_other_violation',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Observe Blocked By Other Violation',
            'status' => 'active',
            'notes' => null,
        ]);

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $blockingRule->id,
            'status' => 'open',
            'rule_title_snapshot' => $blockingRule->title,
            'penalty_units' => 25,
            'occurred_at' => '2026-03-23 10:20:00',
            'notes' => 'Existing open non-observe violation.',
            'reported_by_user_id' => $mentor->id,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->artisan(EvaluateObserveTheTimeViolationsCommand::class)
            ->expectsOutput('Evaluated Observe the time for 1 students.')
            ->assertSuccessful();

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'status' => 'open',
        ]);

        $taskSession->refresh();

        $this->assertSame('active', $taskSession->status);
        $this->assertNull($taskSession->ended_at);

        Carbon::setTestNow();
    }

    public function test_command_does_not_create_repeated_observe_the_time_violations_while_one_is_open(): void
    {
        Carbon::setTestNow('2026-03-23 10:36:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $rule = RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'observe_not_repeated_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Observe Not Repeated Student',
            'status' => 'active',
            'notes' => null,
        ]);

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $rule->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Observe the time',
            'penalty_units' => 0,
            'occurred_at' => '2026-03-23 10:30:00',
            'notes' => 'Existing automatic observe violation.',
            'reported_by_user_id' => null,
            'auto_generated_key' => 'observe-time:overtime:session:1:threshold:2026-03-23T10:30:00+00:00',
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->artisan(EvaluateObserveTheTimeViolationsCommand::class)
            ->expectsOutput('Evaluated Observe the time for 1 students.')
            ->assertSuccessful();

        $this->assertDatabaseCount('violations', 1);

        $taskSession->refresh();

        $this->assertSame('active', $taskSession->status);
        $this->assertNull($taskSession->ended_at);

        Carbon::setTestNow();
    }

    public function test_command_creates_idle_schedule_observe_the_time_violation_without_page_load(): void
    {
        Carbon::setTestNow('2026-03-23 10:16:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'observe_idle_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Observe Idle Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'schedule_template_id' => null,
            'schedule_name_snapshot' => 'Morning',
            'schedule_weekday_snapshot' => 'Sunday',
            'schedule_notes_snapshot' => null,
            'status' => 'active',
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Math',
            'planned_duration_minutes' => 10,
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-23 10:10:00'),
            'duration_seconds' => 600,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->artisan(EvaluateObserveTheTimeViolationsCommand::class)
            ->expectsOutput('Evaluated Observe the time for 1 students.')
            ->assertSuccessful();

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'status' => 'open',
        ]);

        Carbon::setTestNow();
    }

    public function test_command_does_not_create_stale_idle_schedule_observe_the_time_violation(): void
    {
        Carbon::setTestNow('2026-03-23 10:35:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'observe_stale_idle_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Observe Stale Idle Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'schedule_template_id' => null,
            'schedule_name_snapshot' => 'Morning',
            'schedule_weekday_snapshot' => 'Sunday',
            'schedule_notes_snapshot' => null,
            'status' => 'active',
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Math',
            'planned_duration_minutes' => 10,
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-23 10:10:00'),
            'duration_seconds' => 600,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->artisan(EvaluateObserveTheTimeViolationsCommand::class)
            ->expectsOutput('Evaluated Observe the time for 1 students.')
            ->assertSuccessful();

        $this->assertDatabaseCount('violations', 0);

        Carbon::setTestNow();
    }

    public function test_command_creates_no_task_observe_the_time_violation_without_page_load(): void
    {
        Carbon::setTestNow('2026-03-23 10:16:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'observe_no_task_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Observe No Task Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading',
            'summary' => null,
            'instructions' => null,
            'default_duration_minutes' => 30,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Reading',
            'task_summary_snapshot' => null,
            'task_instructions_snapshot' => null,
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-23 09:30:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-23 10:10:00'),
            'duration_seconds' => 2400,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->artisan(EvaluateObserveTheTimeViolationsCommand::class)
            ->expectsOutput('Evaluated Observe the time for 1 students.')
            ->assertSuccessful();

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'status' => 'open',
        ]);

        Carbon::setTestNow();
    }
}
