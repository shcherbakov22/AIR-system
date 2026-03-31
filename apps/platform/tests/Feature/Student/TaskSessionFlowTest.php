<?php

namespace Tests\Feature\Student;

use App\Enums\UserRole;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
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
