<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\ScheduleWeekday;
use App\Models\ScheduleRun;
use App\Models\Student;
use App\Models\TaskAssignment;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskSessionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function createStudent(User $user, string $displayName): Student
    {
        return Student::create([
            'user_id' => $user->id,
            'display_name' => $displayName,
            'status' => 'active',
            'notes' => null,
        ]);
    }

    protected function createTaskTemplate(User $admin, string $title): TaskTemplate
    {
        return TaskTemplate::create([
            'title' => $title,
            'summary' => 'Work through the task carefully.',
            'instructions' => 'Complete the assigned task.',
            'default_duration_minutes' => 35,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
    }

    protected function createAssignment(User $admin, Student $student, TaskTemplate $taskTemplate): TaskAssignment
    {
        return TaskAssignment::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'assigned',
            'due_on' => '2026-03-10',
            'notes' => 'Complete the work before review.',
        ]);
    }

    public function test_admin_can_view_an_empty_task_session_list(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_task_sessions',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskSessions/Index')
                ->has('taskSessions', 0)
                ->has('students', 0)
                ->where('filters.student_id', '')
                ->where('filters.status', '')
                ->where('metrics.total', 0)
                ->where('metrics.active', 0)
                ->where('metrics.completed', 0)
            );
    }

    public function test_admin_can_view_existing_task_sessions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_task_sessions',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_task_sessions',
        ]);

        $student = $this->createStudent($studentUser, 'Student Task Sessions');
        $taskTemplate = $this->createTaskTemplate($admin, 'Essay Draft');
        $taskAssignment = $this->createAssignment($admin, $student, $taskTemplate);

        TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => $taskAssignment->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Essay Draft',
            'task_summary_snapshot' => 'Work through the task carefully.',
            'task_instructions_snapshot' => 'Complete the assigned task.',
            'assignment_notes_snapshot' => 'Complete the work before review.',
            'planned_duration_minutes' => 35,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-07 09:35:00'),
            'duration_seconds' => 2100,
            'completion_notes' => 'Finished the full draft.',
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskSessions/Index')
                ->has('taskSessions', 1)
                ->where('taskSessions.0.student.display_name', 'Student Task Sessions')
                ->where('taskSessions.0.task_title', 'Essay Draft')
                ->where('taskSessions.0.status', 'completed')
                ->where('taskSessions.0.task_assignment.status', 'assigned')
                ->where('taskSessions.0.duration_label', '35 минут')
                ->where('taskSessions.0.completion_notes', 'Finished the full draft.')
            );
    }

    public function test_admin_can_filter_task_sessions_by_student_and_status(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_task_sessions',
        ]);

        $firstStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_alpha',
        ]);

        $secondStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_beta',
        ]);

        $firstStudent = $this->createStudent($firstStudentUser, 'Student Alpha');
        $secondStudent = $this->createStudent($secondStudentUser, 'Student Beta');

        $readingTemplate = $this->createTaskTemplate($admin, 'Reading Review');
        $writingTemplate = $this->createTaskTemplate($admin, 'Writing Sprint');

        $firstAssignment = $this->createAssignment($admin, $firstStudent, $readingTemplate);
        $secondAssignment = $this->createAssignment($admin, $secondStudent, $writingTemplate);

        TaskSession::create([
            'student_id' => $firstStudent->id,
            'task_assignment_id' => $firstAssignment->id,
            'task_template_id' => $readingTemplate->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Reading Review',
            'task_summary_snapshot' => 'Work through the task carefully.',
            'task_instructions_snapshot' => 'Complete the assigned task.',
            'assignment_notes_snapshot' => 'Complete the work before review.',
            'planned_duration_minutes' => 35,
            'started_at' => CarbonImmutable::parse('2026-03-07 08:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-07 08:40:00'),
            'duration_seconds' => 2400,
            'completion_notes' => 'Finished the reading notes.',
            'started_by_user_id' => $firstStudentUser->id,
            'stopped_by_user_id' => $firstStudentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $firstStudent->id,
            'task_assignment_id' => $firstAssignment->id,
            'task_template_id' => $readingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading Review',
            'task_summary_snapshot' => 'Work through the task carefully.',
            'task_instructions_snapshot' => 'Complete the assigned task.',
            'assignment_notes_snapshot' => 'Complete the work before review.',
            'planned_duration_minutes' => 35,
            'started_at' => CarbonImmutable::parse('2026-03-07 10:00:00'),
            'started_by_user_id' => $firstStudentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $secondStudent->id,
            'task_assignment_id' => $secondAssignment->id,
            'task_template_id' => $writingTemplate->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Writing Sprint',
            'task_summary_snapshot' => 'Work through the task carefully.',
            'task_instructions_snapshot' => 'Complete the assigned task.',
            'assignment_notes_snapshot' => 'Complete the work before review.',
            'planned_duration_minutes' => 35,
            'started_at' => CarbonImmutable::parse('2026-03-07 11:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-07 11:20:00'),
            'duration_seconds' => 1200,
            'completion_notes' => 'Finished the sprint.',
            'started_by_user_id' => $secondStudentUser->id,
            'stopped_by_user_id' => $secondStudentUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-sessions.index', [
                'student_id' => $firstStudent->id,
                'status' => 'completed',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskSessions/Index')
                ->has('taskSessions', 1)
                ->where('filters.student_id', (string) $firstStudent->id)
                ->where('filters.status', 'completed')
                ->where('taskSessions.0.student.display_name', 'Student Alpha')
                ->where('taskSessions.0.status', 'completed')
                ->where('taskSessions.0.task_title', 'Reading Review')
            );
    }

    public function test_admin_can_view_schedule_run_task_sessions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_task_sessions',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_task_sessions',
        ]);

        $student = $this->createStudent($studentUser, 'Student Task Sessions');
        $taskTemplate = $this->createTaskTemplate($admin, 'Essay Draft');

        $scheduleTemplate = $student->scheduleTemplates()->create([
            'name' => 'Tuesday Run',
            'weekday' => ScheduleWeekday::Tuesday,
            'is_active' => true,
            'notes' => 'Schedule note.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleEntry = $scheduleTemplate->entries()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 35,
            'notes' => 'Schedule block note.',
        ]);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'schedule_template_id' => $scheduleTemplate->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Tuesday Run',
            'schedule_weekday_snapshot' => 'Tuesday',
            'schedule_notes_snapshot' => 'Schedule note.',
            'started_at' => CarbonImmutable::parse('2026-03-07 09:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $scheduleRunBlock = $scheduleRun->blocks()->create([
            'schedule_entry_id' => $scheduleEntry->id,
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'status' => 'in_progress',
            'start_time_snapshot' => '09:00',
            'duration_minutes_snapshot' => 35,
            'task_title_snapshot' => 'Essay Draft',
            'task_summary_snapshot' => 'Work through the task carefully.',
            'task_instructions_snapshot' => 'Complete the assigned task.',
            'entry_notes_snapshot' => 'Schedule block note.',
            'started_at' => CarbonImmutable::parse('2026-03-07 09:00:00'),
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $scheduleRunBlock->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Essay Draft',
            'task_summary_snapshot' => 'Work through the task carefully.',
            'task_instructions_snapshot' => 'Complete the assigned task.',
            'assignment_notes_snapshot' => 'Schedule block note.',
            'planned_duration_minutes' => 35,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskSessions/Index')
                ->where('taskSessions.0.source_type', 'schedule')
                ->where('taskSessions.0.schedule_run.name', 'Tuesday Run')
                ->where('taskSessions.0.schedule_run_block.position', 1)
                ->where('taskSessions.0.context_notes', 'Schedule block note.')
            );
    }

    public function test_admin_can_view_paused_and_ad_hoc_task_sessions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_task_sessions',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_task_sessions',
        ]);

        $student = $this->createStudent($studentUser, 'Student Task Sessions');
        $taskTemplate = $this->createTaskTemplate($admin, 'Essay Draft');

        $scheduleTemplate = $student->scheduleTemplates()->create([
            'name' => 'Tuesday Run',
            'weekday' => ScheduleWeekday::Tuesday,
            'is_active' => true,
            'notes' => 'Schedule note.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleEntry = $scheduleTemplate->entries()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 35,
            'notes' => 'Schedule block note.',
        ]);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'schedule_template_id' => $scheduleTemplate->id,
            'status' => 'paused',
            'schedule_name_snapshot' => 'Tuesday Run',
            'schedule_weekday_snapshot' => 'Tuesday',
            'schedule_notes_snapshot' => 'Schedule note.',
            'started_at' => CarbonImmutable::parse('2026-03-07 09:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $scheduleRunBlock = $scheduleRun->blocks()->create([
            'schedule_entry_id' => $scheduleEntry->id,
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'status' => 'paused',
            'start_time_snapshot' => '09:00',
            'duration_minutes_snapshot' => 35,
            'task_title_snapshot' => 'Essay Draft',
            'task_summary_snapshot' => 'Work through the task carefully.',
            'task_instructions_snapshot' => 'Complete the assigned task.',
            'entry_notes_snapshot' => 'Schedule block note.',
            'started_at' => CarbonImmutable::parse('2026-03-07 09:00:00'),
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => null,
            'status' => 'active',
            'task_title_snapshot' => 'Break Timer',
            'task_summary_snapshot' => 'Urgent interruption.',
            'task_instructions_snapshot' => null,
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 15,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:20:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $scheduleRunBlock->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'paused',
            'task_title_snapshot' => 'Essay Draft',
            'task_summary_snapshot' => 'Work through the task carefully.',
            'task_instructions_snapshot' => 'Complete the assigned task.',
            'assignment_notes_snapshot' => 'Paused for an interruption.',
            'planned_duration_minutes' => 35,
            'started_at' => CarbonImmutable::parse('2026-03-07 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-07 09:12:00'),
            'duration_seconds' => 720,
            'completion_notes' => 'Paused for an ad hoc timer.',
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskSessions/Index')
                ->where('filters.status', '')
                ->where('metrics.paused', 1)
                ->has('taskSessions', 2)
                ->where('taskSessions.0.source_type', 'ad_hoc')
                ->where('taskSessions.0.status', 'active')
                ->where('taskSessions.1.source_type', 'schedule')
                ->where('taskSessions.1.status', 'paused')
            );
    }

    public function test_students_are_redirected_away_from_task_session_routes(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_task_sessions',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.task-sessions.index'))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
