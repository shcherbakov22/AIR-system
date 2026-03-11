<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_an_empty_task_library(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_tasks',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskTemplates/Index')
                ->has('taskTemplates', 0)
            );
    }

    public function test_admin_can_view_the_create_task_template_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_tasks',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-templates.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/TaskTemplates/Create'));
    }

    public function test_admin_can_create_a_task_template(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_tasks',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.task-templates.store'), [
            'title' => 'Math Drill',
            'summary' => 'Short arithmetic practice block.',
            'instructions' => 'Work through the worksheet without skipping problems.',
            'default_duration_minutes' => 25,
            'is_active' => true,
        ]);

        $response
            ->assertRedirect(route('admin.task-templates.index', absolute: false))
            ->assertSessionHas('success', 'Task template Math Drill has been created.');

        $this->assertDatabaseHas('task_templates', [
            'title' => 'Math Drill',
            'summary' => null,
            'instructions' => 'Work through the worksheet without skipping problems.',
            'default_duration_minutes' => 25,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_view_existing_task_templates(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_tasks',
        ]);

        TaskTemplate::create([
            'title' => 'Reading Session',
            'summary' => 'Focused reading block.',
            'instructions' => 'Read quietly and summarize the chapter afterward.',
            'default_duration_minutes' => 40,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskTemplates/Index')
                ->has('taskTemplates', 1)
                ->where('taskTemplates.0.title', 'Reading Session')
                ->where('taskTemplates.0.default_duration_minutes', 40)
            );
    }

    public function test_admin_can_delete_a_task_template(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_tasks',
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Session',
            'summary' => 'Focused reading block.',
            'instructions' => 'Read quietly and summarize the chapter afterward.',
            'default_duration_minutes' => 40,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.task-templates.destroy', $taskTemplate))
            ->assertRedirect(route('admin.task-templates.index', absolute: false))
            ->assertSessionHas('success', 'Task template Reading Session has been deleted.');

        $this->assertDatabaseMissing('task_templates', [
            'id' => $taskTemplate->id,
        ]);
    }

    public function test_students_are_redirected_away_from_task_template_routes(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_tasks',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.task-templates.index'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('admin.task-templates.create'))
            ->assertRedirect(route('dashboard', absolute: false));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_tasks_owner',
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Session',
            'summary' => 'Focused reading block.',
            'instructions' => 'Read quietly and summarize the chapter afterward.',
            'default_duration_minutes' => 40,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->delete(route('admin.task-templates.destroy', $taskTemplate))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
