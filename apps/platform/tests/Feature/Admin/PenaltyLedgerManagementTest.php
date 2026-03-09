<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\PenaltyAccount;
use App\Models\PenaltyTransaction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PenaltyLedgerManagementTest extends TestCase
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

    public function test_admin_can_view_penalty_ledger_index(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_penalties',
        ]);

        $student = $this->createStudent('student_penalties', 'Student Penalties');
        $account = PenaltyAccount::create([
            'student_id' => $student->id,
        ]);

        PenaltyTransaction::create([
            'penalty_account_id' => $account->id,
            'violation_id' => null,
            'type' => 'manual_charge',
            'delta_units' => 12,
            'notes' => 'Initial balance.',
            'recorded_at' => '2026-03-08 10:00:00',
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.penalties.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Penalties/Index')
                ->has('studentLedgers', 1)
                ->where('studentLedgers.0.student.display_name', 'Student Penalties')
                ->where('studentLedgers.0.current_balance_units', 12)
                ->where('studentLedgers.0.transaction_count', 1)
            );
    }

    public function test_admin_can_view_student_penalty_ledger_and_account_is_created_if_missing(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_penalties',
        ]);

        $student = $this->createStudent('student_penalties', 'Student Penalties');

        $this->assertDatabaseCount('penalty_accounts', 0);

        $this->actingAs($admin)
            ->get(route('admin.penalties.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Penalties/Show')
                ->where('student.display_name', 'Student Penalties')
                ->where('penaltyAccount.current_balance_units', 0)
                ->has('transactions', 0)
            );

        $this->assertDatabaseHas('penalty_accounts', [
            'student_id' => $student->id,
        ]);
    }

    public function test_admin_can_post_a_manual_penalty_charge(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_penalties',
        ]);

        $student = $this->createStudent('student_penalties', 'Student Penalties');

        $response = $this->actingAs($admin)
            ->post(route('admin.penalties.transactions.store', $student), [
                'transaction_type' => 'manual_charge',
                'amount_units' => 15,
                'notes' => 'Assigned penalty after review.',
            ]);

        $response
            ->assertRedirect(route('admin.penalties.show', $student, absolute: false))
            ->assertSessionHas('success', 'Начисление штрафа на 15 ед. проведено.');

        $this->assertDatabaseHas('penalty_transactions', [
            'type' => 'manual_charge',
            'delta_units' => 15,
            'notes' => 'Assigned penalty after review.',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_post_a_manual_penalty_credit(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_penalties',
        ]);

        $student = $this->createStudent('student_penalties', 'Student Penalties');
        $account = PenaltyAccount::create([
            'student_id' => $student->id,
        ]);

        PenaltyTransaction::create([
            'penalty_account_id' => $account->id,
            'violation_id' => null,
            'type' => 'manual_charge',
            'delta_units' => 20,
            'notes' => 'Initial penalty.',
            'recorded_at' => '2026-03-08 10:00:00',
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.penalties.transactions.store', $student), [
                'transaction_type' => 'manual_credit',
                'amount_units' => 5,
                'notes' => 'Reduced after admin review.',
            ]);

        $response
            ->assertRedirect(route('admin.penalties.show', $student, absolute: false))
            ->assertSessionHas('success', 'Списание штрафа на 5 ед. проведено.');

        $this->assertDatabaseHas('penalty_transactions', [
            'type' => 'manual_credit',
            'delta_units' => -5,
            'notes' => 'Reduced after admin review.',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_not_credit_more_than_the_current_balance(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_penalties',
        ]);

        $student = $this->createStudent('student_penalties', 'Student Penalties');
        $account = PenaltyAccount::create([
            'student_id' => $student->id,
        ]);

        PenaltyTransaction::create([
            'penalty_account_id' => $account->id,
            'violation_id' => null,
            'type' => 'manual_charge',
            'delta_units' => 4,
            'notes' => 'Small balance.',
            'recorded_at' => '2026-03-08 10:00:00',
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->from(route('admin.penalties.show', $student))
            ->actingAs($admin)
            ->post(route('admin.penalties.transactions.store', $student), [
                'transaction_type' => 'manual_credit',
                'amount_units' => 9,
                'notes' => 'Too large.',
            ]);

        $response
            ->assertRedirect(route('admin.penalties.show', $student, absolute: false))
            ->assertSessionHasErrors([
                'amount_units' => 'Сумма списания не может превышать текущий штрафной баланс.',
            ]);

        $this->assertDatabaseCount('penalty_transactions', 1);
    }

    public function test_students_are_redirected_away_from_penalty_routes(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_penalties',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.penalties.index'))
            ->assertRedirect(route('dashboard', absolute: false));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_penalties_forbidden',
        ]);

        $student = $this->createStudent('student_penalties_owner', 'Student Penalties Owner');

        $this->actingAs($studentUser)
            ->get(route('admin.penalties.show', $student))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($studentUser)
            ->post(route('admin.penalties.transactions.store', $student), [
                'transaction_type' => 'manual_charge',
                'amount_units' => 10,
                'notes' => 'Forbidden.',
            ])
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
