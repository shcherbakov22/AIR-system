<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\TaskTemplate;
use App\Models\TaskSession;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_enroll_device_and_receive_token(): void
    {
        [$student, $studentUser] = $this->makeStudent('companion_student', 'secret-pass');

        $response = $this->postJson(route('api.companion.enroll'), [
            'username' => $studentUser->username,
            'password' => 'secret-pass',
            'device_key' => 'device-alpha',
            'label' => 'Desk PC',
            'hostname' => 'desk-pc',
            'platform' => 'windows',
            'app_version' => '0.1.0',
            'meta' => [
                'build' => 'debug',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('student.id', $student->id)
            ->assertJsonPath('device.device_key', 'device-alpha');

        $this->assertDatabaseHas('student_devices', [
            'student_id' => $student->id,
            'device_key' => 'device-alpha',
            'label' => 'Desk PC',
            'platform' => 'windows',
        ]);

        $device = StudentDevice::query()->firstOrFail();
        $this->assertNotNull($device->token_hash);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_policy_blocks_internet_for_non_internet_task_and_unread_messages_and_open_schedules(): void
    {
        [$student, $studentUser] = $this->makeStudent('policy_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');

        $taskTemplate = TaskTemplate::create([
            'title' => 'Tennis',
            'summary' => null,
            'instructions' => 'Practice serves.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'created_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Tennis',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(2),
            'duration_seconds' => 120,
            'started_by_user_id' => $studentUser->id,
        ]);

        $token = $device->issueToken();

        $policyResponse = $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'));

        $policyResponse
            ->assertOk()
            ->assertJsonPath('policy.task.requires_internet', false)
            ->assertJsonPath('policy.internet_policy.internet_allowed', false)
            ->assertJsonPath('policy.internet_policy.reason', 'task_blocks_internet');

        TaskSession::query()->delete();

        ScheduleRun::create([
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Morning',
            'schedule_weekday_snapshot' => 'monday',
            'schedule_notes_snapshot' => null,
            'started_at' => now()->subMinutes(5),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.internet_policy.internet_allowed', false)
            ->assertJsonPath('policy.internet_policy.reason', 'open_schedule_without_active_task');

        ScheduleRun::query()->delete();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_policy',
        ]);

        $student->forceFill([
            'last_seen_mentor_chat_at' => null,
            'last_seen_announcements_at' => null,
        ])->save();

        $student->chatMessages()->create([
            'channel' => 'chat',
            'sender_user_id' => $admin->id,
            'body' => 'Read this before starting.',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.communication_gate.has_unread', true)
            ->assertJsonPath('policy.internet_policy.reason', 'communication_gate')
            ->assertJsonPath('policy.internet_policy.internet_allowed', false);
    }

    public function test_policy_allows_internet_for_task_that_requires_it(): void
    {
        [$student, $studentUser] = $this->makeStudent('internet_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');

        $coding = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build features.',
            'default_duration_minutes' => 60,
            'requires_internet' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $coding->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 60,
            'started_at' => now()->subMinutes(10),
            'duration_seconds' => 600,
            'started_by_user_id' => $studentUser->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($device->issueToken()))
            ->getJson(route('api.companion.policy.show'));

        $response
            ->assertOk()
            ->assertJsonPath('policy.internet_policy.internet_allowed', true)
            ->assertJsonPath('policy.internet_policy.reason', 'task_requires_internet');
    }

    public function test_device_can_post_heartbeat_activity_and_capture(): void
    {
        Storage::fake('local');

        [$student, $studentUser] = $this->makeStudent('capture_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $taskTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build features.',
            'default_duration_minutes' => 45,
            'requires_internet' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 45,
            'started_at' => now()->subMinutes(4),
            'duration_seconds' => 240,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.heartbeat'), [
                'label' => 'Desk PC',
                'hostname' => 'desk-pc',
                'app_version' => '0.1.0',
                'meta' => ['health' => 'ok'],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'focused_app',
                'app_name' => 'Code.exe',
                'window_title' => 'AIR System',
                'browser_domain' => 'github.com',
                'payload' => ['pid' => 1234],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $captureResponse = $this->withHeaders($this->authHeaders($token))
            ->post(route('api.companion.captures.screen'), [
                'capture' => UploadedFile::fake()->create('screen.jpg', 256, 'image/jpeg'),
                'captured_at' => now()->toAtomString(),
                'app_name' => 'Code.exe',
                'window_title' => 'AIR System',
                'browser_domain' => 'github.com',
            ]);

        $captureResponse
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseCount('device_heartbeats', 1);
        $this->assertDatabaseHas('device_activity_events', [
            'student_device_id' => $device->id,
            'event_type' => 'focused_app',
            'app_name' => 'Code.exe',
            'browser_domain' => 'github.com',
        ]);
        $this->assertDatabaseHas('student_monitor_captures', [
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'capture_kind' => 'screen',
            'task_title_snapshot' => 'Coding',
            'app_name_snapshot' => 'Code.exe',
            'browser_domain_snapshot' => 'github.com',
        ]);
    }

    public function test_device_command_flow_is_scoped_and_result_submission_is_idempotent(): void
    {
        [$student, $studentUser] = $this->makeStudent('command_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $otherDevice = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'other-device',
            'label' => 'Other',
            'platform' => 'windows',
        ]);

        $token = $device->issueToken();
        $otherToken = $otherDevice->issueToken();

        $command = $device->commands()->create([
            'command_type' => 'refresh_policy',
            'status' => 'pending',
            'payload' => ['source' => 'test'],
            'requested_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.commands.next'))
            ->assertOk()
            ->assertJsonPath('command.id', $command->id)
            ->assertJsonPath('command.command_type', 'refresh_policy');

        $this->withHeaders($this->authHeaders($otherToken))
            ->postJson(route('api.companion.commands.acknowledge', $command))
            ->assertNotFound();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.commands.acknowledge', $command))
            ->assertOk()
            ->assertJsonPath('command.status', 'leased');

        $resultPayload = [
            'status' => 'completed',
            'payload' => ['exit_code' => 0],
        ];

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.commands.result', $command), $resultPayload)
            ->assertOk()
            ->assertJsonPath('result.status', 'completed');

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.commands.result', $command), $resultPayload)
            ->assertOk()
            ->assertJsonPath('result.status', 'completed');

        $this->assertDatabaseCount('device_command_results', 1);
        $this->assertDatabaseHas('device_commands', [
            'id' => $command->id,
            'status' => 'completed',
        ]);
    }

    public function test_revoked_device_cannot_fetch_policy_and_token_can_be_renewed(): void
    {
        [$student, $studentUser] = $this->makeStudent('revoke_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $renewResponse = $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.token.renew'));

        $renewResponse
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $newToken = $renewResponse->json('token');

        $this->withHeaders($this->authHeaders($newToken))
            ->postJson(route('api.companion.revoke'))
            ->assertOk()
            ->assertJsonPath('revoked', true);

        $this->withHeaders($this->authHeaders($newToken))
            ->getJson(route('api.companion.policy.show'))
            ->assertForbidden();
    }

    private function makeStudent(string $username, string $password): array
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => $username,
            'password' => Hash::make($password),
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => ucfirst($username),
            'status' => 'active',
            'notes' => null,
        ]);

        return [$student, $studentUser];
    }

    private function enrollDevice(User $studentUser, string $password): StudentDevice
    {
        $this->postJson(route('api.companion.enroll'), [
            'username' => $studentUser->username,
            'password' => $password,
            'device_key' => 'device-'.strtolower($studentUser->username),
            'label' => 'Desk PC',
            'hostname' => 'desk-pc',
            'platform' => 'windows',
            'app_version' => '0.1.0',
        ])->assertOk();

        return StudentDevice::query()->where('device_key', 'device-'.strtolower($studentUser->username))->firstOrFail();
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
