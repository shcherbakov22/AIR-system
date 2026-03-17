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
use App\Services\GatewayPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CompanionGatewayPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.network_control.enabled', true);
    }

    public function test_gateway_ruleset_blocks_manually_blocked_devices_but_keeps_air_and_dns_reachable(): void
    {
        config()->set('services.network_control.gateway_server_ipv4', '192.168.11.228');
        config()->set('services.network_control.gateway_dns_ipv4', '192.168.11.228');
        config()->set('services.network_control.gateway_table_name', 'air_companion');

        [$blockedStudent, $blockedUser] = $this->makeStudent('blocked_student');
        [$allowedStudent, $allowedUser] = $this->makeStudent('allowed_student');

        StudentDevice::create([
            'student_id' => $blockedStudent->id,
            'device_key' => 'blocked-device',
            'label' => 'Blocked Device',
            'platform' => 'windows',
            'last_ipv4' => '192.168.11.50',
            'internet_access_mode' => 'block_all',
        ]);

        StudentDevice::create([
            'student_id' => $allowedStudent->id,
            'device_key' => 'allowed-device',
            'label' => 'Allowed Device',
            'platform' => 'windows',
            'last_ipv4' => '192.168.11.51',
            'internet_access_mode' => 'allow_all',
        ]);

        $ruleset = app(GatewayPolicyService::class)->buildRuleset();

        $this->assertStringContainsString('table inet air_companion', $ruleset);
        $this->assertStringContainsString('ip saddr 192.168.11.50 ip daddr 192.168.11.228 tcp dport { 80, 443 } accept', $ruleset);
        $this->assertStringContainsString('ip saddr 192.168.11.50 ip daddr 192.168.11.228 udp dport 53 accept', $ruleset);
        $this->assertStringContainsString('ip saddr 192.168.11.50 drop', $ruleset);
        $this->assertStringNotContainsString('192.168.11.51 drop', $ruleset);
    }

    public function test_gateway_sync_command_applies_rules_with_nft(): void
    {
        config()->set('services.network_control.gateway_nft_binary', 'nft');
        config()->set('services.network_control.gateway_table_name', 'air_companion');

        [$student] = $this->makeStudent('command_gateway_student');

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'command-device',
            'label' => 'Command Device',
            'platform' => 'windows',
            'last_ipv4' => '192.168.11.60',
            'internet_access_mode' => 'block_all',
        ]);

        Process::fake();

        $this->artisan('gateway:sync-device-policies')
            ->expectsOutput('Gateway rules synced for 1 devices.')
            ->assertExitCode(0);

        Process::assertRanTimes(function ($process) {
            return $process->command === ['nft', 'delete', 'table', 'inet', 'air_companion'];
        }, 1);

        Process::assertRan(function ($process) {
            return $process->command === ['nft', '-f', '-']
                && str_contains($process->input, '192.168.11.60')
                && str_contains($process->input, 'drop');
        });
    }

    private function makeStudent(string $username): array
    {
        $user = User::factory()->create([
            'role' => UserRole::Student,
            'username' => $username,
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'display_name' => ucfirst(str_replace('_', ' ', $username)),
            'status' => 'active',
            'notes' => null,
        ]);

        return [$student, $user];
    }
}
