<?php

namespace Tests\Feature\Student;

use App\Enums\UserRole;
use App\Models\PenaltyAccount;
use App\Models\PenaltyTransaction;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PenaltyViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_penalty_details_with_violations_and_ledger_history(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_penalty_view',
            'name' => 'Student Penalty View',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Penalty View Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_penalty_view',
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on task',
            'description' => 'Student must stay on the active task.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 11,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $openViolation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on task',
            'penalty_units' => 11,
            'occurred_at' => '2026-03-08 12:05:00',
            'notes' => 'Left the task without permission.',
            'reported_by_user_id' => $admin->id,
        ]);

        $resolvedViolation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'resolved',
            'rule_title_snapshot' => 'Stay on task',
            'penalty_units' => 4,
            'occurred_at' => '2026-03-08 09:30:00',
            'notes' => 'Earlier issue that was already reviewed.',
            'reported_by_user_id' => $admin->id,
        ]);

        $account = PenaltyAccount::create([
            'student_id' => $student->id,
        ]);

        PenaltyTransaction::create([
            'penalty_account_id' => $account->id,
            'violation_id' => $openViolation->id,
            'type' => 'violation_charge',
            'delta_units' => 11,
            'notes' => 'Automatic charge from violation #1.',
            'recorded_at' => '2026-03-08 12:05:00',
            'created_by_user_id' => $admin->id,
        ]);

        PenaltyTransaction::create([
            'penalty_account_id' => $account->id,
            'violation_id' => null,
            'type' => 'manual_credit',
            'delta_units' => -3,
            'notes' => 'Reduced after admin review.',
            'recorded_at' => '2026-03-08 13:00:00',
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.penalties.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Penalties/Index')
                ->where('student.display_name', 'Penalty View Student')
                ->where('penaltySummary.current_balance_units', 8)
                ->where('penaltySummary.open_violations', 1)
                ->where('penaltySummary.transaction_count', 2)
                ->has('violations', 2)
                ->where('violations.0.id', $openViolation->id)
                ->where('violations.0.status', 'open')
                ->where('violations.0.posted_units', 11)
                ->where('violations.1.id', $resolvedViolation->id)
                ->where('transactions.0.type', 'manual_credit')
                ->where('transactions.0.delta_units', -3)
                ->where('transactions.1.type', 'violation_charge')
                ->where('transactions.1.violation.id', $openViolation->id)
            );
    }

    public function test_admins_are_redirected_away_from_student_penalties_page(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_student_penalties_forbidden',
        ]);

        $this->actingAs($admin)
            ->get(route('student.penalties.index'))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
