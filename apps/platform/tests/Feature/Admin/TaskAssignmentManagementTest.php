<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\TaskAssignment;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskAssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_an_empty_assignment_list(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_assignments',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-assignments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskAssignments/Index')
                ->has('taskAssignments', 0)
            );
    }

    public function test_admin_can_view_the_create_assignment_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_assignments',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_assignments',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Assignments',
            'status' => 'active',
            'notes' => null,
        ]);

        TaskTemplate::create([
            'title' => 'Science Reading',
            'summary' => 'Read and recap.',
            'instructions' => 'Read the chapter and summarize it.',
            'default_duration_minutes' => 35,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-assignments.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskAssignments/Create')
                ->has('students', 1)
                ->has('taskTemplates', 1)
            );
    }

    public function test_admin_can_create_a_task_assignment(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_assignments',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_assignments',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Assignments',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Science Reading',
            'summary' => 'Read and recap.',
            'instructions' => 'Read the chapter and summarize it.',
            'default_duration_minutes' => 35,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.task-assignments.store'), [
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'assigned',
            'due_on' => '2026-03-09',
            'notes' => 'Complete before dinner.',
        ]);

        $response
            ->assertRedirect(route('admin.task-assignments.index', absolute: false))
            ->assertSessionHas('success', 'Назначение задания Science Reading создано.');

        $this->assertDatabaseHas('task_assignments', [
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'assigned',
            'notes' => 'Complete before dinner.',
        ]);

        $assignment = TaskAssignment::query()->firstOrFail();

        $this->assertSame('2026-03-09', $assignment->due_on?->toDateString());
    }

    public function test_admin_can_view_the_edit_assignment_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_assignments',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_assignments',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Assignments',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Science Reading',
            'summary' => 'Read and recap.',
            'instructions' => 'Read the chapter and summarize it.',
            'default_duration_minutes' => 35,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $taskAssignment = TaskAssignment::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'assigned',
            'due_on' => '2026-03-09',
            'notes' => 'Complete before dinner.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-assignments.edit', $taskAssignment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskAssignments/Edit')
                ->where('taskAssignment.id', $taskAssignment->id)
                ->where('taskAssignment.status', 'assigned')
                ->has('students', 1)
                ->has('taskTemplates', 1)
            );
    }

    public function test_admin_can_update_a_task_assignment(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_assignments',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_assignments',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Assignments',
            'status' => 'active',
            'notes' => null,
        ]);

        $secondStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_reassigned',
        ]);

        $secondStudent = Student::create([
            'user_id' => $secondStudentUser->id,
            'display_name' => 'Student Reassigned',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Science Reading',
            'summary' => 'Read and recap.',
            'instructions' => 'Read the chapter and summarize it.',
            'default_duration_minutes' => 35,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $secondTaskTemplate = TaskTemplate::create([
            'title' => 'Writing Sprint',
            'summary' => 'Write the response.',
            'instructions' => 'Draft the short answer response.',
            'default_duration_minutes' => 50,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $taskAssignment = TaskAssignment::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'assigned',
            'due_on' => '2026-03-09',
            'notes' => 'Complete before dinner.',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.task-assignments.update', $taskAssignment), [
            'student_id' => $secondStudent->id,
            'task_template_id' => $secondTaskTemplate->id,
            'status' => 'completed',
            'due_on' => '2026-03-11',
            'notes' => 'Marked complete after review.',
        ]);

        $response
            ->assertRedirect(route('admin.task-assignments.index', absolute: false))
            ->assertSessionHas('success', 'Назначение задания Writing Sprint обновлено.');

        $this->assertDatabaseHas('task_assignments', [
            'id' => $taskAssignment->id,
            'student_id' => $secondStudent->id,
            'task_template_id' => $secondTaskTemplate->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'completed',
            'notes' => 'Marked complete after review.',
        ]);

        $taskAssignment->refresh();

        $this->assertSame('2026-03-11', $taskAssignment->due_on?->toDateString());
    }

    public function test_admin_can_keep_an_inactive_current_template_when_updating(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_assignments',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_assignments',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Assignments',
            'status' => 'active',
            'notes' => null,
        ]);

        $inactiveTaskTemplate = TaskTemplate::create([
            'title' => 'Archived Reading',
            'summary' => 'Legacy task.',
            'instructions' => 'Keep the older assignment intact.',
            'default_duration_minutes' => 25,
            'is_active' => false,
            'created_by_user_id' => $admin->id,
        ]);

        $taskAssignment = TaskAssignment::create([
            'student_id' => $student->id,
            'task_template_id' => $inactiveTaskTemplate->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'assigned',
            'due_on' => '2026-03-09',
            'notes' => 'Existing assignment before archive.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-assignments.edit', $taskAssignment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskAssignments/Edit')
                ->has('taskTemplates', 1)
                ->where('taskTemplates.0.id', $inactiveTaskTemplate->id)
                ->where('taskTemplates.0.is_active', false)
            );

        $this->actingAs($admin)
            ->put(route('admin.task-assignments.update', $taskAssignment), [
                'student_id' => $student->id,
                'task_template_id' => $inactiveTaskTemplate->id,
                'status' => 'paused',
                'due_on' => '2026-03-10',
                'notes' => 'Paused after archive.',
            ])
            ->assertRedirect(route('admin.task-assignments.index', absolute: false))
            ->assertSessionHas('success', 'Назначение задания Archived Reading обновлено.');

        $taskAssignment->refresh();

        $this->assertSame('paused', $taskAssignment->status);
        $this->assertSame('2026-03-10', $taskAssignment->due_on?->toDateString());
        $this->assertSame('Paused after archive.', $taskAssignment->notes);
    }

    public function test_admin_can_view_existing_assignments(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_assignments',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_assignments',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Assignments',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Science Reading',
            'summary' => 'Read and recap.',
            'instructions' => 'Read the chapter and summarize it.',
            'default_duration_minutes' => 35,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        TaskAssignment::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'paused',
            'due_on' => '2026-03-09',
            'notes' => 'Resume tomorrow.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-assignments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskAssignments/Index')
                ->has('taskAssignments', 1)
                ->where('taskAssignments.0.student.display_name', 'Student Assignments')
                ->where('taskAssignments.0.task_template.title', 'Science Reading')
                ->where('taskAssignments.0.status', 'paused')
            );
    }

    public function test_students_are_redirected_away_from_assignment_routes(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_assignments',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.task-assignments.index'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('admin.task-assignments.create'))
            ->assertRedirect(route('dashboard', absolute: false));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_forbidden_assignments',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Assignments',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Science Reading',
            'summary' => 'Read and recap.',
            'instructions' => 'Read the chapter and summarize it.',
            'default_duration_minutes' => 35,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $taskAssignment = TaskAssignment::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'assigned_by_user_id' => $admin->id,
            'status' => 'assigned',
            'due_on' => '2026-03-09',
            'notes' => 'Complete before dinner.',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.task-assignments.edit', $taskAssignment))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
