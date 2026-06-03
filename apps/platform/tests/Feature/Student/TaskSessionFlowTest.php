<?php

namespace Tests\Feature\Student;

use App\Enums\UserRole;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\StudentSetting;
use App\Models\TaskAssignment;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\Violation;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskSessionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function createObserveTheTimeRule(User $mentor): RuleDefinition
    {
        return RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);
    }

    protected function createTaskCompletedTooQuicklyRule(User $mentor): RuleDefinition
    {
        return RuleDefinition::create([
            'title' => 'Task completed too quickly',
            'description' => 'Imported automatic rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);
    }

    protected function createAssignedTask(User $admin, Student $student, string $title = 'Math Review'): TaskAssignment
    {
        $taskTemplate = TaskTemplate::create([
            'title' => $title,
            'summary' => 'Review the assigned work.',
            'instructions' => 'Complete the work carefully.',
            'default_duration_minutes' => 30,
            'can_end_early' => true,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        return TaskAssignment::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'assigned',
            'due_on' => '2026-03-08',
            'notes' => 'Finish before lunch.',
        ]);
    }

    protected function createSleepingTemplate(User $admin): TaskTemplate
    {
        return TaskTemplate::create([
            'title' => 'Sleeping',
            'summary' => 'Sleep.',
            'instructions' => 'Go to sleep.',
            'default_duration_minutes' => 900,
            'can_end_early' => true,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_student_home_shows_the_active_timer_without_assignment_or_recent_work_sections(): void
    {
        Carbon::setTestNow('2026-03-07 09:20:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_sessions',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_sessions',
            'name' => 'Student Sessions',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Sessions',
            'status' => 'active',
            'notes' => 'Ready to work.',
        ]);

        $firstAssignment = $this->createAssignedTask($admin, $student, 'Reading Review');
        $secondAssignment = $this->createAssignedTask($admin, $student, 'Writing Review');

        TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $firstAssignment->id,
            'task_template_id' => $firstAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:15:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $secondAssignment->id,
            'task_template_id' => $secondAssignment->task_template_id,
            'status' => 'completed',
            'task_title_snapshot' => 'Writing Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-06 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-06 10:32:00'),
            'duration_seconds' => 1920,
            'completion_notes' => 'Finished the full response.',
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('serverNow', '2026-03-07T09:20:00+00:00')
                ->where('violationSummary.open_violations', 0)
                ->where('activeTaskSession.task_title', 'Reading Review')
                ->where('activeTaskSession.task_assignment_id', $firstAssignment->id)
                ->missing('taskAssignments')
                ->missing('recentTaskSessions')
            );

        Carbon::setTestNow();
    }

    public function test_student_can_start_custom_timer_without_an_open_schedule(): void
    {
        Carbon::setTestNow('2026-03-07 09:20:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_custom_timer_start',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_custom_timer_start',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Custom Timer Start',
            'status' => 'active',
            'notes' => null,
        ]);

        $breakTemplate = TaskTemplate::create([
            'title' => 'Break Timer',
            'summary' => 'Short break.',
            'instructions' => 'Return when finished.',
            'default_duration_minutes' => 15,
            'can_interrupt_schedule' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.task-sessions.custom-timer'), [
                'task_template_id' => $breakTemplate->id,
                'duration_minutes' => 7,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Custom timer started.');

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Break Timer',
            'planned_duration_minutes' => 7,
            'duration_seconds' => 0,
        ]);

        Carbon::setTestNow();
    }

    public function test_student_can_interrupt_active_task_for_allowed_custom_timer(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_custom_timer_interrupt',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_custom_timer_interrupt',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Custom Timer Interrupt',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($admin, $student);
        $activeTaskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $breakTemplate = TaskTemplate::create([
            'title' => 'Water',
            'summary' => 'Drink water.',
            'instructions' => 'Take a short water break.',
            'default_duration_minutes' => 5,
            'can_interrupt_schedule' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.task-sessions.custom-timer'), [
                'task_template_id' => $breakTemplate->id,
                'duration_minutes' => 3,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Current task paused. Custom timer started.');

        $this->assertDatabaseHas('task_sessions', [
            'id' => $activeTaskSession->id,
            'status' => 'unfinished',
            'duration_seconds' => 1800,
            'completion_notes' => 'Paused for a custom timer.',
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Water',
            'planned_duration_minutes' => 3,
            'duration_seconds' => 0,
        ]);

        Carbon::setTestNow();
    }

    public function test_student_can_stop_an_active_task_session_and_complete_the_assignment(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_sessions',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_sessions',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Sessions',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->createSleepingTemplate($admin);
        $taskAssignment = $this->createAssignedTask($admin, $student);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $response = $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession));

        Carbon::setTestNow();

        $response
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Task session Math Review finished. Sleeping started.');

        $this->assertDatabaseHas('task_sessions', [
            'id' => $taskSession->id,
            'status' => 'completed',
            'duration_seconds' => 1800,
            'completion_notes' => null,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->assertDatabaseHas('task_assignments', [
            'id' => $taskAssignment->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Sleeping',
            'started_by_user_id' => $studentUser->id,
        ]);
    }

    public function test_stopping_an_ad_hoc_task_starts_sleeping_when_no_schedule_remains(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_sleep_transition',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_sleep_transition',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Sleep Transition',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->createSleepingTemplate($admin);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading',
            'summary' => 'Read.',
            'instructions' => 'Keep reading.',
            'default_duration_minutes' => 55,
            'can_end_early' => true,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'task_summary_snapshot' => 'Read.',
            'task_instructions_snapshot' => 'Keep reading.',
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 55,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Sleeping started.'));

        $this->assertDatabaseHas('task_sessions', [
            'id' => $taskSession->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Sleeping',
            'started_by_user_id' => $studentUser->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_task_marked_can_end_early_can_finish_before_eighty_percent_without_too_short_violation(): void
    {
        Carbon::setTestNow('2026-03-07 11:12:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_early_finish_allowed',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_early_finish_allowed',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Early Finish Allowed',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->createObserveTheTimeRule($admin);
        $this->createTaskCompletedTooQuicklyRule($admin);
        $this->createSleepingTemplate($admin);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Tennis',
            'summary' => 'Practice tennis.',
            'instructions' => 'Practice with focus.',
            'default_duration_minutes' => 55,
            'can_end_early' => true,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Tennis',
            'task_summary_snapshot' => 'Practice tennis.',
            'task_instructions_snapshot' => 'Practice with focus.',
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 55,
            'started_at' => CarbonImmutable::parse('2026-03-07 11:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Tennis'));

        $this->assertDatabaseHas('task_sessions', [
            'id' => $taskSession->id,
            'status' => 'completed',
            'duration_seconds' => 720,
        ]);

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Task completed too quickly',
            'auto_generated_key' => 'observe-time:too-short:session:'.$taskSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_stopping_sleeping_does_not_immediately_restart_sleeping(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_stop_sleeping',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_stop_sleeping',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Stop Sleeping',
            'status' => 'active',
            'notes' => null,
        ]);

        $sleepingTemplate = $this->createSleepingTemplate($admin);

        $sleepingSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $sleepingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Sleeping',
            'task_summary_snapshot' => 'Sleep.',
            'task_instructions_snapshot' => 'Go to sleep.',
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 900,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $sleepingSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Task session Sleeping finished.');

        $this->assertDatabaseHas('task_sessions', [
            'id' => $sleepingSession->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseMissing('task_sessions', [
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Sleeping',
        ]);

        Carbon::setTestNow();
    }

    public function test_student_can_mark_an_active_task_session_unfinished_and_resume_it(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_unfinished_sessions',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_unfinished_sessions',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Unfinished Sessions',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($admin, $student);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.unfinished', $taskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Task session Math Review marked unfinished.');

        $this->assertDatabaseHas('task_sessions', [
            'id' => $taskSession->id,
            'status' => 'unfinished',
            'duration_seconds' => 1800,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('pausedTaskSession.task_title', 'Math Review')
                ->where('pausedTaskSession.duration_seconds', 1800)
            );

        Carbon::setTestNow('2026-03-07 11:10:00');

        $this->actingAs($studentUser)
            ->post(route('student.task-sessions.resume', $taskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Task session Math Review resumed.');

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'duration_seconds' => 1800,
            'started_by_user_id' => $studentUser->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_finishing_a_resumed_unfinished_task_early_does_not_create_too_short_violation(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_unfinished_too_short',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_unfinished_too_short',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Unfinished Too Short',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->createObserveTheTimeRule($admin);
        $this->createTaskCompletedTooQuicklyRule($admin);
        $taskAssignment = $this->createAssignedTask($admin, $student);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:57:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.unfinished', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        Carbon::setTestNow('2026-03-07 11:05:00');

        $this->actingAs($studentUser)
            ->post(route('student.task-sessions.resume', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $resumedTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        $this->assertSame($taskSession->id, $resumedTaskSession->resumed_from_task_session_id);

        Carbon::setTestNow('2026-03-07 11:07:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $resumedTaskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Math Review'));

        $this->assertDatabaseHas('task_sessions', [
            'id' => $resumedTaskSession->id,
            'status' => 'completed',
            'duration_seconds' => 300,
            'resumed_from_task_session_id' => $taskSession->id,
        ]);

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Task completed too quickly',
            'auto_generated_key' => 'observe-time:too-short:session:'.$resumedTaskSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_marking_task_unfinished_removes_existing_too_short_violation(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_unfinished_existing_violation',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_unfinished_existing_violation',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Unfinished Existing Violation',
            'status' => 'active',
            'notes' => null,
        ]);

        $tooShortRule = $this->createTaskCompletedTooQuicklyRule($admin);
        $taskAssignment = $this->createAssignedTask($admin, $student);
        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'completed',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'duration_seconds' => 8,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:59:52'),
            'ended_at' => CarbonImmutable::parse('2026-03-07 11:00:00'),
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);
        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $tooShortRule->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Task completed too quickly',
            'penalty_units' => 10,
            'occurred_at' => now(),
            'auto_generated_key' => 'observe-time:too-short:session:'.$taskSession->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.unfinished', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Task completed too quickly',
            'auto_generated_key' => 'observe-time:too-short:session:'.$taskSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_finishing_a_task_resets_the_students_look_away_counter(): void
    {
        Carbon::setTestNow('2026-03-07 10:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_lookaway_reset',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_lookaway_reset',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Lookaway Reset',
            'status' => 'active',
            'notes' => null,
        ]);

        StudentSetting::create([
            'student_id' => $student->id,
            'can_manage_own_schedule' => true,
            'can_use_ad_hoc_timer' => true,
            'look_away_event_threshold' => 3,
            'look_away_event_count' => 2,
            'look_away_task_session_id' => null,
            'preferred_timezone' => 'UTC',
        ]);

        $taskAssignment = $this->createAssignedTask($admin, $student);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(10),
            'duration_seconds' => 600,
            'started_by_user_id' => $admin->id,
        ]);

        $student->setting()->update([
            'look_away_task_session_id' => $taskSession->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
        ]);
    }

    public function test_student_home_exposes_unfinished_action_for_schedule_blocks_with_existing_task_sessions(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedule_unfinished',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedule_unfinished',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedule Unfinished',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($admin, $student);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Morning Run',
            'schedule_weekday_snapshot' => 'friday',
            'schedule_notes_snapshot' => null,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $completedBlock = ScheduleRunBlock::create([
            'schedule_run_id' => $scheduleRun->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'position' => 1,
            'status' => 'completed',
            'start_time_snapshot' => '10:00',
            'duration_minutes_snapshot' => 30,
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'entry_notes_snapshot' => null,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'completed_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $completedBlock->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'duration_seconds' => 1800,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun.blocks.0.actual_duration_label', '30:00')
                ->where('activeScheduleRun.blocks.0.unfinished_url', route('student.task-sessions.unfinished', $taskSession))
            );

        Carbon::setTestNow();
    }

    public function test_student_can_continue_schedule_after_marking_a_schedule_task_unfinished(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedule_continue',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedule_continue',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedule Continue',
            'status' => 'active',
            'notes' => null,
        ]);

        $firstAssignment = $this->createAssignedTask($admin, $student, 'Math Review');
        $secondTemplate = TaskTemplate::create([
            'title' => 'Reading Review',
            'summary' => 'Read the next section.',
            'instructions' => 'Read carefully.',
            'default_duration_minutes' => 20,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Morning Run',
            'schedule_weekday_snapshot' => 'friday',
            'schedule_notes_snapshot' => null,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $firstBlock = ScheduleRunBlock::create([
            'schedule_run_id' => $scheduleRun->id,
            'task_template_id' => $firstAssignment->task_template_id,
            'position' => 1,
            'status' => 'in_progress',
            'start_time_snapshot' => '10:00',
            'duration_minutes_snapshot' => 30,
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'entry_notes_snapshot' => null,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
        ]);

        $secondBlock = ScheduleRunBlock::create([
            'schedule_run_id' => $scheduleRun->id,
            'task_template_id' => $secondTemplate->id,
            'position' => 2,
            'status' => 'pending',
            'start_time_snapshot' => '11:00',
            'duration_minutes_snapshot' => 20,
            'task_title_snapshot' => 'Reading Review',
            'task_summary_snapshot' => 'Read the next section.',
            'task_instructions_snapshot' => 'Read carefully.',
            'entry_notes_snapshot' => null,
        ]);

        $activeTaskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $firstAssignment->id,
            'task_template_id' => $firstAssignment->task_template_id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $firstBlock->id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.unfinished', $activeTaskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Task session Math Review marked unfinished.');

        $this->assertDatabaseHas('task_sessions', [
            'id' => $activeTaskSession->id,
            'status' => 'completed',
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $firstBlock->id,
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'status' => 'unfinished',
            'schedule_run_id' => null,
            'schedule_run_block_id' => $firstBlock->id,
            'task_title_snapshot' => 'Math Review',
        ]);

        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $firstBlock->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('schedule_runs', [
            'id' => $scheduleRun->id,
            'status' => 'active',
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [$scheduleRun, $secondBlock]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Task session Reading Review started.');

        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $secondBlock->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $secondBlock->id,
            'task_title_snapshot' => 'Reading Review',
        ]);

        Carbon::setTestNow();
    }

    public function test_student_home_marks_schedule_unfinished_blocks_as_unfinished_with_resume_url(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedule_resume_payload',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedule_resume_payload',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedule Resume Payload',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($admin, $student);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Morning Run',
            'schedule_weekday_snapshot' => 'friday',
            'schedule_notes_snapshot' => null,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $unfinishedBlock = ScheduleRunBlock::create([
            'schedule_run_id' => $scheduleRun->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'position' => 1,
            'status' => 'completed',
            'start_time_snapshot' => '10:00',
            'duration_minutes_snapshot' => 30,
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'entry_notes_snapshot' => null,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'completed_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $unfinishedBlock->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'duration_seconds' => 1800,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $unfinishedTaskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'schedule_run_id' => null,
            'schedule_run_block_id' => $unfinishedBlock->id,
            'status' => 'unfinished',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'duration_seconds' => 1800,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('pausedTaskSession', null)
                ->where('activeScheduleRun.blocks.0.status_label', 'unfinished')
                ->where('activeScheduleRun.blocks.0.resume_url', route('student.task-sessions.resume', $unfinishedTaskSession))
                ->where('activeScheduleRun.blocks.0.unfinished_url', null)
            );

        Carbon::setTestNow();
    }

    public function test_student_can_resume_a_schedule_unfinished_task_while_schedule_remains_active(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedule_resume',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedule_resume',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedule Resume',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($admin, $student);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Morning Run',
            'schedule_weekday_snapshot' => 'friday',
            'schedule_notes_snapshot' => null,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $unfinishedBlock = ScheduleRunBlock::create([
            'schedule_run_id' => $scheduleRun->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'position' => 1,
            'status' => 'completed',
            'start_time_snapshot' => '10:00',
            'duration_minutes_snapshot' => 30,
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'entry_notes_snapshot' => null,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'completed_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
        ]);

        $unfinishedTaskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'schedule_run_id' => null,
            'schedule_run_block_id' => $unfinishedBlock->id,
            'status' => 'unfinished',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'duration_seconds' => 1800,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.task-sessions.resume', $unfinishedTaskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Task session Math Review resumed.');

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_run_id' => null,
            'schedule_run_block_id' => $unfinishedBlock->id,
            'task_title_snapshot' => 'Math Review',
            'duration_seconds' => 1800,
        ]);

        $this->assertDatabaseMissing('task_sessions', [
            'id' => $unfinishedTaskSession->id,
        ]);

        $resumedTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->where('schedule_run_block_id', $unfinishedBlock->id)
            ->sole();

        Carbon::setTestNow('2026-03-07 11:10:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $resumedTaskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Math Review'));

        $this->assertDatabaseHas('task_sessions', [
            'id' => $resumedTaskSession->id,
            'status' => 'completed',
            'duration_seconds' => 2400,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun.blocks.0.actual_duration_seconds', 2400)
                ->where('activeScheduleRun.blocks.0.actual_duration_label', '40:00')
            );

        Carbon::setTestNow();
    }

    public function test_student_stop_stores_whole_duration_seconds_when_timestamps_include_microseconds(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:10.500000');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_sessions',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_sessions',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Sessions',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($admin, $student);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 11:00:00.100000'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        Carbon::setTestNow();

        $taskSession->refresh();

        $this->assertSame(10, $taskSession->duration_seconds);
    }

    public function test_student_unfinished_stores_whole_duration_seconds_when_timestamps_include_microseconds(): void
    {
        Carbon::setTestNow('2026-03-07 11:00:10.500000');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_unfinished_microseconds',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_unfinished_microseconds',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Unfinished Microseconds',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($admin, $student);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 11:00:00.200000'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.unfinished', $taskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Task session Math Review marked unfinished.');

        Carbon::setTestNow();

        $taskSession->refresh();

        $this->assertSame('unfinished', $taskSession->status);
        $this->assertSame(10, $taskSession->duration_seconds);
    }

    public function test_student_can_not_stop_another_students_task_session(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_sessions',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_sessions',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Sessions',
            'status' => 'active',
            'notes' => null,
        ]);

        $otherStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'other_student_sessions',
        ]);

        $otherStudent = Student::create([
            'user_id' => $otherStudentUser->id,
            'display_name' => 'Other Student Sessions',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($admin, $otherStudent);

        $taskSession = TaskSession::create([
            'student_id' => $otherStudent->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:30:00'),
            'started_by_user_id' => $otherStudentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertNotFound();

        $this->assertDatabaseHas('task_sessions', [
            'id' => $taskSession->id,
            'status' => 'active',
            'stopped_by_user_id' => null,
        ]);
    }

    public function test_overdue_task_creates_one_automatic_observe_the_time_violation_when_stopped(): void
    {
        Carbon::setTestNow('2026-03-07 10:00:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_sessions',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Sessions',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($mentor, $student);
        $this->createObserveTheTimeRule($mentor);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:24:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'status' => 'open',
        ]);

        Carbon::setTestNow();
    }

    public function test_stopping_a_task_with_time_remaining_does_not_create_observe_the_time_violation(): void
    {
        Carbon::setTestNow('2026-03-07 09:55:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation_safe_stop',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_sessions_safe_stop',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Sessions Safe Stop',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($mentor, $student);
        $this->createObserveTheTimeRule($mentor);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:25:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 0);

        Carbon::setTestNow();
    }

    public function test_manual_violation_marks_students_active_task_unfinished(): void
    {
        Carbon::setTestNow('2026-03-07 10:00:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_manual_violation_interrupt',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_manual_violation_interrupt',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Manual Violation Interrupt',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($mentor, $student);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:45:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Talking',
            'description' => 'No talking.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $this->actingAs($mentor)
            ->post(route('admin.violations.store'), [
                'student_id' => $student->id,
                'rule_definition_id' => $ruleDefinition->id,
                'occurred_at' => '2026-03-07 10:00:00',
                'notes' => 'Interrupted by violation.',
            ])
            ->assertRedirect();

        $taskSession->refresh();

        $this->assertSame('unfinished', $taskSession->status);
        $this->assertSame(900, $taskSession->duration_seconds);
    }

    public function test_automatic_observe_the_time_violation_marks_active_task_unfinished(): void
    {
        Carbon::setTestNow('2026-03-07 10:00:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation_interrupt',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_auto_violation_interrupt',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Auto Violation Interrupt',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($mentor, $student);
        $this->createObserveTheTimeRule($mentor);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:24:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $taskSession->refresh();

        $this->assertSame('completed', $taskSession->status);
        $this->assertSame(2160, $taskSession->duration_seconds);
        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'status' => 'open',
        ]);
    }

    public function test_home_page_does_not_duplicate_an_existing_overdue_observe_the_time_violation(): void
    {
        Carbon::setTestNow('2026-03-07 10:00:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_sessions',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Sessions',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($mentor, $student);
        $this->createObserveTheTimeRule($mentor);

        TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:24:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('violationSummary.open_violations', 1)
                ->where('openViolations.0.rule_title', 'Observe the time')
            );

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 1);

        Carbon::setTestNow();
    }

    public function test_stopping_an_overdue_task_after_home_created_observe_the_time_does_not_duplicate_the_violation(): void
    {
        Carbon::setTestNow('2026-03-07 10:00:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation_stop_after_home',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_sessions_stop_after_home',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Sessions Stop After Home',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskAssignment = $this->createAssignedTask($mentor, $student);
        $this->createObserveTheTimeRule($mentor);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskAssignment->task_template_id,
            'status' => 'active',
            'task_title_snapshot' => 'Math Review',
            'task_summary_snapshot' => 'Review the assigned work.',
            'task_instructions_snapshot' => 'Complete the work carefully.',
            'assignment_notes_snapshot' => 'Finish before lunch.',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:24:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 1);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseCount('violations', 1);

        Carbon::setTestNow();
    }
}
