<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RuleDefinitionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_an_empty_rule_definition_list(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_rules',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rule-definitions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/RuleDefinitions/Index')
                ->has('ruleDefinitions', 0)
            );
    }

    public function test_admin_can_view_the_create_rule_definition_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_rules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_rules',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Rules',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rule-definitions.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/RuleDefinitions/Create')
                ->has('students', 1)
            );
    }

    public function test_admin_can_create_a_global_rule_definition(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_rules',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.rule-definitions.store'), [
            'title' => 'Stay on assigned work',
            'description' => 'Student must remain on the assigned task during the session.',
            'scope' => 'global',
            'is_active' => true,
        ]);

        $response
            ->assertRedirect(route('admin.rule-definitions.index', absolute: false))
            ->assertSessionHas('success', 'Правило Stay on assigned work создано.');

        $this->assertDatabaseHas('rule_definitions', [
            'title' => 'Stay on assigned work',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_create_a_student_scoped_rule_definition(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_rules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_rules',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Rules',
            'status' => 'active',
            'notes' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.rule-definitions.store'), [
            'title' => 'No unscheduled breaks',
            'description' => 'Student must ask before leaving the desk.',
            'scope' => 'student',
            'student_id' => $student->id,
            'default_penalty_units' => 0,
            'is_active' => true,
        ]);

        $response
            ->assertRedirect(route('admin.rule-definitions.index', absolute: false))
            ->assertSessionHas('success', 'Правило No unscheduled breaks создано.');

        $this->assertDatabaseHas('rule_definitions', [
            'title' => 'No unscheduled breaks',
            'scope' => 'student',
            'student_id' => $student->id,
            'default_penalty_units' => 0,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_the_edit_rule_definition_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_rules',
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Remain on the assigned task.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rule-definitions.edit', $ruleDefinition))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/RuleDefinitions/Edit')
                ->where('ruleDefinition.id', $ruleDefinition->id)
                ->where('ruleDefinition.scope', 'global')
            );
    }

    public function test_admin_can_update_a_rule_definition_and_change_scope(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_rules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_rules',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Rules',
            'status' => 'active',
            'notes' => null,
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'No unscheduled breaks',
            'description' => 'Student must ask before leaving the desk.',
            'scope' => 'student',
            'student_id' => $student->id,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.rule-definitions.update', $ruleDefinition), [
            'title' => 'No unscheduled breaks revised',
            'description' => 'Desk departures require approval.',
            'scope' => 'global',
            'is_active' => false,
        ]);

        $response
            ->assertRedirect(route('admin.rule-definitions.index', absolute: false))
            ->assertSessionHas('success', 'Правило No unscheduled breaks revised обновлено.');

        $this->assertDatabaseHas('rule_definitions', [
            'id' => $ruleDefinition->id,
            'title' => 'No unscheduled breaks revised',
            'description' => 'Desk departures require approval.',
            'scope' => 'global',
            'student_id' => null,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_a_rule_definition(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_rules_delete',
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Remain on the assigned task.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.rule-definitions.destroy', $ruleDefinition))
            ->assertRedirect(route('admin.rule-definitions.index', absolute: false))
            ->assertSessionHas('success', 'Правило Stay on assigned work удалено.');

        $this->assertDatabaseMissing('rule_definitions', [
            'id' => $ruleDefinition->id,
        ]);
    }

    public function test_students_are_redirected_away_from_rule_definition_routes(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_rules',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.rule-definitions.index'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('admin.rule-definitions.create'))
            ->assertRedirect(route('dashboard', absolute: false));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_rules_forbidden',
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Remain on the assigned task.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.rule-definitions.edit', $ruleDefinition))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($studentUser)
            ->delete(route('admin.rule-definitions.destroy', $ruleDefinition))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
