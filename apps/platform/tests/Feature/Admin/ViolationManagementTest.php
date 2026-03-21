<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationResolution;
use Carbon\Carbon;
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
                ->has('students', 0)
                ->has('ruleDefinitions', 0)
                ->where('openViolationCounts', [])
                ->has('openViolations', 0)
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
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        RuleDefinition::create([
            'title' => 'Archived rule',
            'description' => 'Inactive rule.',
            'scope' => 'student',
            'student_id' => $student->id,
            'default_penalty_units' => 0,
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
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.violations.store'), [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'occurred_at' => '2026-03-08T10:15',
            'notes' => 'Observed switching away from the assigned work tab.',
        ]);

        $response
            ->assertRedirect(route('admin.violations.index', absolute: false))
            ->assertSessionHas('success', 'Violation Stay on assigned work created.');

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 10,
            'notes' => 'Observed switching away from the assigned work tab.',
            'reported_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('student_consequence_profiles', [
            'student_id' => $student->id,
            'current_push_up_count' => 11,
        ]);
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
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->from(route('admin.violations.create'))
            ->actingAs($admin)
            ->post(route('admin.violations.store'), [
                'student_id' => $student->id,
                'rule_definition_id' => $ruleDefinition->id,
                'occurred_at' => '2026-03-08T11:00',
                'notes' => 'Invalid pairing.',
            ]);

        $response
            ->assertRedirect(route('admin.violations.create', absolute: false))
            ->assertSessionHasErrors([
                'rule_definition_id' => 'The selected rule does not apply to this student.',
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
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 10,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Left the assigned work page.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.violations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Violations/Index')
                ->has('students', 1)
                ->has('ruleDefinitions', 1)
                ->where('openViolationCounts.'.$student->id.':'.$ruleDefinition->id, 1)
                ->has('openViolations', 1)
                ->where('openViolations.0.student.display_name', 'Student Violations')
                ->where('openViolations.0.rule_title', 'Stay on assigned work')
                ->where('openViolations.0.push_up_count', 10)
                ->where('openViolations.0.status', 'open')
            );
    }

    public function test_admin_can_adjust_student_push_up_counter(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_push_up_counter',
        ]);

        $student = $this->createStudent('student_push_up_counter', 'Student Push Up Counter');

        $this->actingAs($admin)
            ->patch(route('admin.students.push-up-counter.update', $student), [
                'action' => 'increment',
            ])
            ->assertSessionHas('success', 'Push-up counter updated.');

        $this->assertDatabaseHas('student_consequence_profiles', [
            'student_id' => $student->id,
            'current_push_up_count' => 11,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.students.push-up-counter.update', $student), [
                'action' => 'decrement',
            ]);

        $this->assertDatabaseHas('student_consequence_profiles', [
            'student_id' => $student->id,
            'current_push_up_count' => 10,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.students.push-up-counter.update', $student), [
                'action' => 'reset',
            ]);

        $this->assertDatabaseHas('student_consequence_profiles', [
            'student_id' => $student->id,
            'current_push_up_count' => 10,
        ]);
    }

    public function test_admin_can_not_create_a_second_open_violation_for_the_same_student_and_rule(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations_duplicate',
        ]);

        $student = $this->createStudent('student_violations_duplicate', 'Student Violations Duplicate');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 0,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Already open.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->from(route('admin.violations.create'))
            ->actingAs($admin)
            ->post(route('admin.violations.store'), [
                'student_id' => $student->id,
                'rule_definition_id' => $ruleDefinition->id,
                'occurred_at' => '2026-03-08T10:15',
                'notes' => 'Should not duplicate.',
            ])
            ->assertRedirect(route('admin.violations.create', absolute: false))
            ->assertSessionHas('error', 'Violation Stay on assigned work is already open for this student.');

        $this->assertDatabaseCount('violations', 1);
    }

    public function test_matrix_toggle_removes_an_existing_open_violation_for_the_same_student_and_rule(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations_toggle',
        ]);

        $student = $this->createStudent('student_violations_toggle', 'Student Violations Toggle');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 0,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Already open.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.violations.store'), [
                'student_id' => $student->id,
                'rule_definition_id' => $ruleDefinition->id,
                'occurred_at' => '2026-03-08T10:15',
                'notes' => null,
                'toggle' => true,
            ])
            ->assertRedirect(route('admin.violations.index', absolute: false))
            ->assertSessionHas('success', 'Violation Stay on assigned work removed.');

        $this->assertDatabaseMissing('violations', [
            'id' => $violation->id,
        ]);
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
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 0,
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
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 0,
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
            ->assertSessionHas('success', 'Violation Stay on assigned work marked as resolved.');

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
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 0,
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
            ->assertSessionHas('success', 'Violation Stay on assigned work marked as waived.');

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
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'resolved',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 0,
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
            ->assertSessionHas('error', 'This violation is already closed.');

        $this->assertDatabaseCount('violation_resolutions', 1);
    }

    public function test_admin_can_delete_a_violation(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations_delete',
        ]);

        $student = $this->createStudent('student_delete_violation', 'Student Delete Violation');

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on assigned work',
            'description' => 'Student must stay on assigned work.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 0,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Created for delete coverage.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.violations.destroy', $violation))
            ->assertRedirect(route('admin.violations.index', absolute: false))
            ->assertSessionHas('success', 'Violation Stay on assigned work deleted.');

        $this->assertDatabaseMissing('violations', [
            'id' => $violation->id,
        ]);
    }

    public function test_deleting_an_automatic_violation_keeps_it_off_the_student_home_page(): void
    {
        Carbon::setTestNow('2026-03-08 09:50:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_delete_auto_violation',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_delete_auto_violation',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Delete Auto Violation',
            'status' => 'active',
            'notes' => null,
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Observe the time',
            'penalty_units' => 0,
            'occurred_at' => '2026-03-08 09:45:00',
            'auto_generated_key' => 'observe-time:idle:run:5:anchor:2026-03-08T09:40:00+00:00',
            'notes' => 'Automatic violation for staying outside any task for more than 5 minutes after the schedule started.',
            'reported_by_user_id' => null,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.violations.destroy', $violation))
            ->assertRedirect(route('admin.violations.index', absolute: false));

        $this->assertDatabaseMissing('violations', [
            'id' => $violation->id,
        ]);

        $this->assertDatabaseHas('dismissed_automatic_violations', [
            'student_id' => $student->id,
            'auto_generated_key' => 'observe-time:idle:run:5:anchor:2026-03-08T09:40:00+00:00',
            'dismissed_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('violationSummary.open_violations', 0)
                ->has('openViolations', 0)
            );

        $this->assertDatabaseCount('violations', 0);

        Carbon::setTestNow();
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
            'description' => 'Remain on the assigned task.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on assigned work',
            'penalty_units' => 0,
            'occurred_at' => '2026-03-08 09:00:00',
            'notes' => 'Left the assigned work page.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.violations.show', $violation))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($studentUser)
            ->delete(route('admin.violations.destroy', $violation))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
