<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PushUpSession;
use App\Models\Student;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushUpSessionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_push_up_session_for_open_violation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $studentUser = User::factory()->create(['role' => UserRole::Student]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Counter Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => null,
            'status' => 'open',
            'rule_title_snapshot' => 'Observe the time',
            'penalty_units' => 12,
            'occurred_at' => now(),
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.violations.push-up-sessions.store', $violation))
            ->assertRedirect();

        $this->assertDatabaseHas('push_up_sessions', [
            'violation_id' => $violation->id,
            'student_id' => $student->id,
            'status' => 'pending',
            'required_push_ups' => 12,
        ]);
    }

    public function test_completing_push_up_session_resolves_violation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $studentUser = User::factory()->create(['role' => UserRole::Student]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Counter Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => null,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on task',
            'penalty_units' => 10,
            'occurred_at' => now(),
            'reported_by_user_id' => $admin->id,
        ]);

        $stationHeartbeat = $this->actingAs($admin)
            ->postJson(route('push-up-station.heartbeat'), [
                'station_key' => 'station-test',
                'station_name' => 'Station test',
            ])
            ->assertOk()
            ->json();

        $session = PushUpSession::create([
            'student_id' => $student->id,
            'violation_id' => $violation->id,
            'requested_by_user_id' => $admin->id,
            'push_up_station_id' => $stationHeartbeat['station']['id'],
            'status' => 'running',
            'required_push_ups' => 10,
            'configuration' => [
                'sets' => 1,
                'reps' => 10,
                'rest_seconds' => 30,
                'penalty_reps' => 5,
                'drop_threshold' => 20,
                'up_gap' => 6,
                'down_tolerance' => 3,
            ],
        ]);

        $this->actingAs($admin)
            ->patchJson(route('push-up-station.sessions.complete', $session), [
                'station_key' => 'station-test',
            ])
            ->assertOk();

        $this->assertDatabaseHas('violations', [
            'id' => $violation->id,
            'status' => 'resolved',
        ]);

        $this->assertDatabaseHas('push_up_sessions', [
            'id' => $session->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('violation_resolutions', [
            'violation_id' => $violation->id,
            'action' => 'resolved',
        ]);
    }

    public function test_student_can_queue_only_their_own_open_violation(): void
    {
        $studentUser = User::factory()->create(['role' => UserRole::Student]);
        $otherStudentUser = User::factory()->create(['role' => UserRole::Student]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Own Counter Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $otherStudent = Student::create([
            'user_id' => $otherStudentUser->id,
            'display_name' => 'Other Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $ownViolation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => null,
            'status' => 'open',
            'rule_title_snapshot' => 'Own violation',
            'penalty_units' => 10,
            'occurred_at' => now(),
        ]);

        $otherViolation = Violation::create([
            'student_id' => $otherStudent->id,
            'rule_definition_id' => null,
            'status' => 'open',
            'rule_title_snapshot' => 'Other violation',
            'penalty_units' => 11,
            'occurred_at' => now(),
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.violations.push-up-sessions.store', $ownViolation))
            ->assertRedirect();

        $this->actingAs($studentUser)
            ->post(route('student.violations.push-up-sessions.store', $otherViolation))
            ->assertNotFound();
    }
}
