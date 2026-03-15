<?php

namespace Tests\Feature\Student;

use App\Enums\UserRole;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\TaskAssignment;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\User;
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

    protected function createAssignedTask(User $admin, Student $student, string $title = 'Math Review'): TaskAssignment
    {
        $taskTemplate = TaskTemplate::create([
            'title' => $title,
            'summary' => 'Review the assigned work.',
            'instructions' => 'Complete the work carefully.',
            'default_duration_minutes' => 30,
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
            ->assertSessionHas('success', 'Task session Math Review finished.');

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
}
