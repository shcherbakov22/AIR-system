<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\BrowserPolicyRule;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskTemplateUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_edit_task_template_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_tasks_edit',
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Review',
            'summary' => 'Reading summary task.',
            'instructions' => 'Read and summarize.',
            'default_duration_minutes' => 45,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.task-templates.edit', $taskTemplate))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TaskTemplates/Edit')
                ->where('taskTemplate.id', $taskTemplate->id)
                ->where('taskTemplate.title', 'Reading Review')
                ->where('taskTemplate.default_duration_minutes', 45)
                ->where('taskTemplate.browser_allowed_domains', [])
            );
    }

    public function test_admin_can_update_a_task_template(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_tasks_edit',
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Review',
            'summary' => 'Reading summary task.',
            'instructions' => 'Read and summarize.',
            'default_duration_minutes' => 45,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        BrowserPolicyRule::create([
            'student_id' => null,
            'task_template_id' => $taskTemplate->id,
            'created_by_user_id' => $admin->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'old-docs.example',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.task-templates.update', $taskTemplate), [
            'title' => 'Reading Review Updated',
            'instructions' => 'Read carefully and write a three-point recap.',
            'default_duration_minutes' => 60,
            'requires_internet' => true,
            'browser_allowed_domains' => "docs.python.org\npython.org",
        ]);

        $response
            ->assertRedirect(route('admin.task-templates.index', absolute: false))
            ->assertSessionHas('success', 'Task template Reading Review Updated has been updated.');

        $taskTemplate->refresh();

        $this->assertSame('Reading Review Updated', $taskTemplate->title);
        $this->assertNull($taskTemplate->summary);
        $this->assertSame('Read carefully and write a three-point recap.', $taskTemplate->instructions);
        $this->assertSame(60, $taskTemplate->default_duration_minutes);

        $this->assertDatabaseHas('browser_policy_rules', [
            'student_id' => null,
            'task_template_id' => $taskTemplate->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'python.org',
        ]);

        $this->assertDatabaseMissing('browser_policy_rules', [
            'task_template_id' => $taskTemplate->id,
            'value' => 'old-docs.example',
        ]);
    }

    public function test_students_are_redirected_away_from_the_edit_task_template_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_tasks_edit',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_tasks_edit',
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Review',
            'summary' => 'Reading summary task.',
            'instructions' => 'Read and summarize.',
            'default_duration_minutes' => 45,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.task-templates.edit', $taskTemplate))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
