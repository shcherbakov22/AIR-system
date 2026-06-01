<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PushUpSession;
use App\Models\PushUpStation;
use App\Models\Student;
use App\Models\StudentDevice;
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

        $session = PushUpSession::query()->where('violation_id', $violation->id)->firstOrFail();

        $this->assertSame(12, $session->required_push_ups);
        $this->assertSame([
            'sets' => 1,
            'reps' => 12,
            'rest_seconds' => 30,
            'penalty_reps' => 5,
            'drop_threshold' => 20,
            'up_gap' => 6,
            'down_tolerance' => 3,
        ], $session->configuration);
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

    public function test_companion_device_can_claim_and_complete_push_up_session(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $studentUser = User::factory()->create(['role' => UserRole::Student]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Counter Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'companion-device',
            'label' => 'Student PC',
            'hostname' => 'student-pc',
            'platform' => 'windows',
            'app_version' => 'test',
        ]);
        $deviceToken = $device->issueToken();

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => null,
            'status' => 'open',
            'rule_title_snapshot' => 'Stay on task',
            'penalty_units' => 10,
            'occurred_at' => now(),
            'reported_by_user_id' => $admin->id,
        ]);

        PushUpSession::create([
            'student_id' => $student->id,
            'violation_id' => $violation->id,
            'requested_by_user_id' => $admin->id,
            'status' => 'pending',
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
            'current_rep' => 0,
            'current_set' => 1,
        ]);

        $heartbeat = $this
            ->withHeader('Authorization', 'Bearer '.$deviceToken)
            ->postJson(route('api.companion.push-up-station.heartbeat'), [
                'station_key' => 'companion-station',
                'station_name' => 'Companion station',
            ])
            ->assertOk()
            ->json();

        $this->assertSame(1, $heartbeat['pending_count']);
        $this->assertNull($heartbeat['session']);

        $claim = $this
            ->withHeader('Authorization', 'Bearer '.$deviceToken)
            ->postJson(route('api.companion.push-up-station.claim-next'), [
                'station_key' => 'companion-station',
                'station_name' => 'Companion station',
            ])
            ->assertOk()
            ->json();

        $this->assertSame(10, $claim['session']['required_push_ups']);

        $sessionId = $claim['session']['id'];

        $this
            ->withHeader('Authorization', 'Bearer '.$deviceToken)
            ->postJson(route('api.companion.push-up-station.sessions.start', $sessionId), [
                'station_key' => 'companion-station',
            ])
            ->assertOk();

        $this
            ->withHeader('Authorization', 'Bearer '.$deviceToken)
            ->postJson(route('api.companion.push-up-station.sessions.progress', $sessionId), [
                'station_key' => 'companion-station',
                'current_rep' => 10,
                'current_set' => 1,
            ])
            ->assertOk();

        $this
            ->withHeader('Authorization', 'Bearer '.$deviceToken)
            ->postJson(route('api.companion.push-up-station.sessions.complete', $sessionId), [
                'station_key' => 'companion-station',
            ])
            ->assertOk();

        $this->assertDatabaseHas('violations', [
            'id' => $violation->id,
            'status' => 'resolved',
        ]);

        $this->assertDatabaseHas('push_up_sessions', [
            'id' => $sessionId,
            'status' => 'completed',
        ]);
    }

    public function test_direct_station_token_can_claim_and_complete_push_up_session(): void
    {
        config()->set('services.push_up_station.shared_token', 'station-secret');

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

        PushUpSession::create([
            'student_id' => $student->id,
            'violation_id' => $violation->id,
            'requested_by_user_id' => $admin->id,
            'status' => 'pending',
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
            'current_rep' => 0,
            'current_set' => 1,
        ]);

        $this
            ->postJson(route('api.push-up-station.heartbeat'), [
                'station_key' => 'esp32-station',
                'station_name' => 'ESP32 station',
            ])
            ->assertUnauthorized();

        $claim = $this
            ->withHeader('X-Push-Up-Station-Token', 'station-secret')
            ->postJson(route('api.push-up-station.claim-next'), [
                'station_key' => 'esp32-station',
                'station_name' => 'ESP32 station',
            ])
            ->assertOk()
            ->json();

        $sessionId = $claim['session']['id'];
        $this->assertSame(10, $claim['session']['config_reps']);

        $this
            ->withHeader('X-Push-Up-Station-Token', 'station-secret')
            ->postJson(route('api.push-up-station.sessions.start', $sessionId), [
                'station_key' => 'esp32-station',
            ])
            ->assertOk();

        $this
            ->withHeader('X-Push-Up-Station-Token', 'station-secret')
            ->postJson(route('api.push-up-station.sessions.progress', $sessionId), [
                'station_key' => 'esp32-station',
                'current_rep' => 10,
                'current_set' => 1,
            ])
            ->assertOk();

        $this
            ->withHeader('X-Push-Up-Station-Token', 'station-secret')
            ->postJson(route('api.push-up-station.sessions.complete', $sessionId), [
                'station_key' => 'esp32-station',
            ])
            ->assertOk();

        $this->assertDatabaseHas('violations', [
            'id' => $violation->id,
            'status' => 'resolved',
        ]);

        $this->assertDatabaseHas('push_up_sessions', [
            'id' => $sessionId,
            'status' => 'completed',
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

    public function test_queueing_refreshes_stale_pending_push_up_session_configuration(): void
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
            'penalty_units' => 48,
            'occurred_at' => now(),
            'reported_by_user_id' => $admin->id,
        ]);

        $staleSession = PushUpSession::create([
            'student_id' => $student->id,
            'violation_id' => $violation->id,
            'requested_by_user_id' => $admin->id,
            'status' => 'pending',
            'required_push_ups' => 30,
            'configuration' => [
                'sets' => 3,
                'reps' => 10,
                'rest_seconds' => 30,
                'penalty_reps' => 7,
                'drop_threshold' => 18,
                'up_gap' => 5,
                'down_tolerance' => 4,
            ],
            'current_rep' => 8,
            'current_set' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.violations.push-up-sessions.store', $violation))
            ->assertRedirect();

        $staleSession->refresh();

        $this->assertSame(48, $staleSession->required_push_ups);
        $this->assertSame([
            'sets' => 1,
            'reps' => 48,
            'rest_seconds' => 30,
            'penalty_reps' => 5,
            'drop_threshold' => 20,
            'up_gap' => 6,
            'down_tolerance' => 3,
        ], $staleSession->configuration);
        $this->assertSame(0, $staleSession->current_rep);
        $this->assertSame(1, $staleSession->current_set);
    }

    public function test_queueing_requeues_claimed_session_if_station_is_stale(): void
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
            'penalty_units' => 16,
            'occurred_at' => now(),
            'reported_by_user_id' => $admin->id,
        ]);

        $station = PushUpStation::create([
            'station_key' => 'stale-station',
            'name' => 'Stale station',
            'last_seen_at' => now()->subMinutes(5),
            'last_claimed_at' => now()->subMinutes(5),
        ]);

        $staleSession = PushUpSession::create([
            'student_id' => $student->id,
            'violation_id' => $violation->id,
            'requested_by_user_id' => $admin->id,
            'push_up_station_id' => $station->id,
            'status' => 'claimed',
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
            'claimed_at' => now()->subMinutes(5),
            'current_rep' => 4,
            'current_set' => 1,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.violations.push-up-sessions.store', $violation))
            ->assertRedirect();

        $staleSession->refresh();

        $this->assertSame('pending', $staleSession->status);
        $this->assertNull($staleSession->push_up_station_id);
        $this->assertNull($staleSession->claimed_at);
        $this->assertNull($staleSession->started_at);
        $this->assertSame(16, $staleSession->required_push_ups);
        $this->assertSame(0, $staleSession->current_rep);
        $this->assertSame(1, $staleSession->current_set);
    }
}
