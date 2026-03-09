<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\PenaltyAccount;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationResolution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ViolationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function createStudent(string $username, string $displayName): Student
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => $username,
        ]);

        return Student::create([
            'user_id' => $studentUser->id,
            'display_name' => $displayName,
            'status' => 'active',
            'notes' => null,
        ]);
    }

    public function test_admin_can_view_an_empty_violation_list(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.violations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Violations/Index')
                ->has('violations', 0)
            );
    }

    public function test_admin_can_view_the_create_violation_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $student = $this->createStudent('student_violations', 'Student Violations');

        RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 10,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        RuleDefinition::create([
            'title' => 'Archived rule',
            'description' => 'Inactive rule.',
            'scope' => 'student',
            'student_id' => $student->id,
            'default_penalty_units' => 5,
            'is_active' => false,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.violations.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Violations/Create')
                ->has('students', 1)
                ->has('ruleDefinitions', 1)
                ->where('ruleDefinitions.0.title', 'Stay on assigned work')
            );
    }

    public function test_admin_can_create_a_violation_from_a_global_rule(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $student = $this->createStudent('student_violations', 'Student Violations');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 10,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.violations.store'), [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'occurred_at' => '2026-03-08T10:15',
            'penalty_units' => 14,
            'notes' => 'Observed switching away from the assigned work tab.',
        ]);

        $response
            ->assertRedirect(route('admin.violations.index', absolute: false))
            ->assertSessionHas('success', 'Нарушение Stay on assigned work создано, начислено 14 штрафных ед.');

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 14,
            'notes' => 'Observed switching away from the assigned work tab.',
            'reported_by_user_id' => $admin->id,
        ]);

        $account = PenaltyAccount::query()->where('student_id', $student->id)->first();

        $this->assertNotNull($account);

        $this->assertDatabaseHas('penalty_transactions', [
            'penalty_account_id' => $account?->id,
            'type' => 'violation_charge',
            'delta_units' => 14,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_create_a_zero_unit_violation_without_posting_a_penalty_transaction(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_zero_violation',
        ]);

        $student = $this->createStudent('student_zero_violation', 'Student Zero Violation');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Late return from break',
            'description' => 'Minor issue logged for review.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.violations.store'), [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'occurred_at' => '2026-03-08T10:30',
            'penalty_units' => 0,
            'notes' => 'Logged without a penalty charge.',
        ]);

        $response
            ->assertRedirect(route('admin.violations.index', absolute: false))
            ->assertSessionHas('success', 'Нарушение Late return from break создано.');

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'penalty_units' => 0,
        ]);

        $this->assertDatabaseCount('penalty_accounts', 0);
        $this->assertDatabaseCount('penalty_transactions', 0);
    }

    public function test_admin_can_not_create_a_violation_with_a_rule_for_a_different_student(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $student = $this->createStudent('student_violations', 'Student Violations');
        $otherStudent = $this->createStudent('other_student_violations', 'Other Student Violations');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'No unscheduled breaks',
            'description' => 'Student must ask before leaving the desk.',
            'scope' => 'student',
            'student_id' => $otherStudent->id,
            'default_penalty_units' => 8,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->from(route('admin.violations.create'))
            ->actingAs($admin)
            ->post(route('admin.violations.store'), [
                'student_id' => $student->id,
                'rule_definition_id' => $ruleDefinition->id,
                'occurred_at' => '2026-03-08T11:00',
                'penalty_units' => 8,
                'notes' => 'Invalid pairing.',
            ]);

        $response
            ->assertRedirect(route('admin.violations.create', absolute: false))
            ->assertSessionHasErrors([
                'rule_definition_id' => 'Выбранное правило не применяется к этому ученику.',
            ]);

        $this->assertDatabaseCount('violations', 0);
    }

    public function test_admin_can_view_existing_violations(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $student = $this->createStudent('student_violations', 'Student Violations');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 10,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 12,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Left the assigned work page.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.violations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Violations/Index')
                ->has('violations', 1)
                ->where('violations.0.student.display_name', 'Student Violations')
                ->where('violations.0.rule_title', 'Stay on assigned work')
                ->where('violations.0.status', 'open')
                ->where('violations.0.penalty_units', 12)
            );
    }

    public function test_admin_can_view_the_violation_review_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $student = $this->createStudent('student_violations', 'Student Violations');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 10,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 12,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Left the assigned work page.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.violations.show', $violation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Violations/Show')
                ->where('violation.id', $violation->id)
                ->where('violation.status', 'open')
                ->where('violation.rule_title', 'Stay on assigned work')
                ->where('violation.penalty_transaction', null)
                ->has('resolutions', 0)
            );
    }

    public function test_admin_can_resolve_an_open_violation_and_record_a_resolution(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $student = $this->createStudent('student_violations', 'Student Violations');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 10,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 12,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Left the assigned work page.',
            'reported_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.violations.resolve', $violation), [
                'action' => 'resolved',
                'notes' => 'Reviewed and handled directly.',
            ]);

        $response
            ->assertRedirect(route('admin.violations.show', $violation, absolute: false))
            ->assertSessionHas('success', 'Нарушение Stay on assigned work отмечено как решённое.');

        $this->assertDatabaseHas('violations', [
            'id' => $violation->id,
            'status' => 'resolved',
        ]);

        $this->assertDatabaseHas('violation_resolutions', [
            'violation_id' => $violation->id,
            'action' => 'resolved',
            'notes' => 'Reviewed and handled directly.',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_waive_an_open_violation_and_record_a_resolution(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $student = $this->createStudent('student_violations', 'Student Violations');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 10,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 12,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Left the assigned work page.',
            'reported_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.violations.resolve', $violation), [
                'action' => 'waived',
                'notes' => 'Waived after context review.',
            ]);

        $response
            ->assertRedirect(route('admin.violations.show', $violation, absolute: false))
            ->assertSessionHas('success', 'Нарушение Stay on assigned work отмечено как отменённое.');

        $this->assertDatabaseHas('violations', [
            'id' => $violation->id,
            'status' => 'waived',
        ]);

        $this->assertDatabaseHas('violation_resolutions', [
            'violation_id' => $violation->id,
            'action' => 'waived',
            'notes' => 'Waived after context review.',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_not_resolve_an_already_closed_violation(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $student = $this->createStudent('student_violations', 'Student Violations');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 10,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'resolved',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 12,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Left the assigned work page.',
            'reported_by_user_id' => $admin->id,
        ]);

        ViolationResolution::create([
            'violation_id' => $violation->id,
            'action' => 'resolved',
            'notes' => 'Already handled.',
            'recorded_at' => '2026-03-08 10:00:00',
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.violations.resolve', $violation), [
                'action' => 'waived',
                'notes' => 'Should not apply.',
            ]);

        $response
            ->assertRedirect(route('admin.violations.show', $violation, absolute: false))
            ->assertSessionHas('error', 'Это нарушение уже закрыто.');

        $this->assertDatabaseCount('violation_resolutions', 1);
    }

    public function test_students_are_redirected_away_from_violation_routes(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_violations',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.violations.index'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('admin.violations.create'))
            ->assertRedirect(route('dashboard', absolute: false));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations_forbidden',
        ]);

        $student = $this->createStudent('student_violations_owner', 'Student Violations Owner');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 10,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 12,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Left the assigned work page.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.violations.show', $violation))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
