<?php

namespace Tests\Feature\Student;

use App\Enums\UserRole;
use App\Models\RuleDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RuleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_active_rules(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_rules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_rules',
        ]);

        RuleDefinition::create([
            'title' => 'Keep desk clean',
            'description' => 'Workspace must be cleaned before the next block.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 2,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        RuleDefinition::create([
            'title' => 'Daily reading note',
            'description' => 'Leave a reading note after each session.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 3,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        RuleDefinition::create([
            'title' => 'Archived global rule',
            'description' => 'Inactive rule should stay hidden.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 1,
            'is_active' => false,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.rules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Rules/Index')
                ->where('ruleSummary.total', 2)
                ->has('rules', 2)
                ->where('rules.0.title', 'Daily reading note')
                ->where('rules.1.title', 'Keep desk clean')
            );
    }

    public function test_admin_cannot_open_student_rules_page(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_rules',
        ]);

        $this->actingAs($admin)
            ->get(route('student.rules.index'))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
