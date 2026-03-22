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

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.network_control.enabled', true);
    }

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

    public function test_policy_allows_internet_by_default_when_network_control_is_enabled(): void
    {
        [$student, $studentUser] = $this->makeStudent('policy_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');

        $token = $device->issueToken();

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'));
        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.internet_policy.mode', 'allow_all')
            ->assertJsonPath('policy.internet_policy.internet_allowed', true)
            ->assertJsonPath('policy.internet_policy.reason', 'admin_device_allow');
    }

    public function test_policy_blocks_internet_when_device_is_manually_blocked(): void
    {
        [$student, $studentUser] = $this->makeStudent('internet_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $device->update([
            'internet_access_mode' => 'block_all',
        ]);

        $response = $this->withHeaders($this->authHeaders($device->issueToken()))
            ->getJson(route('api.companion.policy.show'));

        $response
            ->assertOk()
            ->assertJsonPath('policy.internet_policy.mode', 'block_all')
            ->assertJsonPath('policy.internet_policy.internet_allowed', false)
            ->assertJsonPath('policy.internet_policy.reason', 'admin_device_block');
    }

    public function test_policy_reports_internet_control_as_disabled_when_feature_is_paused(): void
    {
        config()->set('services.network_control.enabled', false);

        [$student, $studentUser] = $this->makeStudent('disabled_internet_student', 'secret-pass');
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
            'started_at' => now()->subMinutes(1),
            'duration_seconds' => 60,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($device->issueToken()))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.internet_policy.mode', 'allow_all')
            ->assertJsonPath('policy.internet_policy.internet_allowed', true)
            ->assertJsonPath('policy.internet_policy.reason', 'internet_control_disabled');
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
                'ipv4' => '192.168.11.44',
                'mac_address' => 'aa:bb:cc:dd:ee:ff',
                'gateway_ipv4' => '192.168.11.228',
                'network_adapter_name' => 'Ethernet 1',
                'remote_control_ready' => true,
                'remote_control_failure_reason' => '',
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
        $this->assertDatabaseHas('student_devices', [
            'id' => $device->id,
            'last_ipv4' => '192.168.11.44',
            'last_mac_address' => 'aa:bb:cc:dd:ee:ff',
            'last_gateway_ipv4' => '192.168.11.228',
            'network_adapter_name' => 'Ethernet 1',
            'remote_control_ready' => true,
        ]);
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

    public function test_first_open_apps_snapshot_is_grandfathered_and_later_new_apps_require_review(): void
    {
        [$student, $studentUser] = $this->makeStudent('app_review_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'Code.exe',
                            'window_title' => 'AIR System',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'app_key' => 'code.exe',
            'app_name' => 'Code.exe',
            'status' => 'permitted',
        ]);

        $device->refresh();
        $this->assertNotNull(data_get($device->meta, 'app_policy_initialized_at'));

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'Code.exe',
                            'window_title' => 'AIR System',
                        ],
                        [
                            'app_name' => 'Steam.exe',
                            'window_title' => 'Steam',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'app_key' => 'steam.exe',
            'app_name' => 'Steam.exe',
            'status' => 'pending_review',
        ]);

        $policyResponse = $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'));

        $policyResponse
            ->assertOk()
            ->assertJsonPath('policy.app_control.mode', 'review')
            ->assertJsonPath('policy.app_control.blocked_processes', []);
    }

    public function test_installed_apps_inventory_is_persisted_and_grandfathered_as_permitted(): void
    {
        [$student, $studentUser] = $this->makeStudent('installed_apps_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'installed_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'Code.exe',
                            'display_name' => 'Visual Studio Code',
                            'display_version' => '1.2.3',
                            'publisher' => 'Microsoft',
                            'install_location' => 'C:\\Program Files\\VS Code',
                            'source' => 'registry_uninstall',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('student_device_installed_apps', [
            'student_device_id' => $device->id,
            'app_key' => 'code.exe',
            'display_name' => 'Visual Studio Code',
            'display_version' => '1.2.3',
            'publisher' => 'Microsoft',
            'install_location' => 'C:\\Program Files\\VS Code',
        ]);

        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'app_key' => 'code.exe',
            'app_name' => 'Visual Studio Code',
            'status' => 'permitted',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'Code.exe',
                            'window_title' => 'AIR System',
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'Game.exe',
                            'window_title' => 'New Game',
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $policy = $student->appPolicies()->where('app_key', 'game.exe')->firstOrFail();
        $policy->update([
            'grace_deadline_at' => now()->subSecond(),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.app_control.blocked_processes.0', 'Game.exe');
    }

    public function test_ready_heartbeat_clears_stale_remote_control_failure_reason(): void
    {
        [$student, $studentUser] = $this->makeStudent('remote_ready_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $device->update([
            'remote_control_ready' => false,
            'remote_control_failure_reason' => 'old failure',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.heartbeat'), [
                'label' => 'Desk PC',
                'remote_control_ready' => true,
                'meta' => [],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $device->refresh();

        $this->assertTrue($device->remote_control_ready);
        $this->assertNull($device->remote_control_failure_reason);
        $this->assertNotNull($device->remote_control_last_checked_at);
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
