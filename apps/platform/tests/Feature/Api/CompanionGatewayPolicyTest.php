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

    public function test_gateway_ruleset_blocks_devices_but_keeps_air_and_dns_reachable(): void
    {
        config()->set('services.network_control.gateway_server_ipv4', '192.168.11.228');
        config()->set('services.network_control.gateway_dns_ipv4', '192.168.11.228');
        config()->set('services.network_control.gateway_table_name', 'air_companion');

        [$blockedStudent, $blockedUser] = $this->makeStudent('blocked_student');
        [$allowedStudent, $allowedUser] = $this->makeStudent('allowed_student');

        $blockedTask = TaskTemplate::create([
            'title' => 'Tennis',
            'summary' => null,
            'instructions' => 'Practice serves.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'created_by_user_id' => $blockedUser->id,
        ]);

        $allowedTask = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build features.',
            'default_duration_minutes' => 60,
            'requires_internet' => true,
            'created_by_user_id' => $allowedUser->id,
        ]);

        TaskSession::create([
            'student_id' => $blockedStudent->id,
            'task_template_id' => $blockedTask->id,
            'status' => 'active',
            'task_title_snapshot' => 'Tennis',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(3),
            'duration_seconds' => 180,
            'started_by_user_id' => $blockedUser->id,
        ]);

        TaskSession::create([
            'student_id' => $allowedStudent->id,
            'task_template_id' => $allowedTask->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 60,
            'started_at' => now()->subMinutes(3),
            'duration_seconds' => 180,
            'started_by_user_id' => $allowedUser->id,
        ]);

        StudentDevice::create([
            'student_id' => $blockedStudent->id,
            'device_key' => 'blocked-device',
            'label' => 'Blocked Device',
            'platform' => 'windows',
            'last_ipv4' => '192.168.11.50',
        ]);

        StudentDevice::create([
            'student_id' => $allowedStudent->id,
            'device_key' => 'allowed-device',
            'label' => 'Allowed Device',
            'platform' => 'windows',
            'last_ipv4' => '192.168.11.51',
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

        [$student, $studentUser] = $this->makeStudent('command_gateway_student');

        $task = TaskTemplate::create([
            'title' => 'Tennis',
            'summary' => null,
            'instructions' => 'Practice serves.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'created_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $task->id,
            'status' => 'active',
            'task_title_snapshot' => 'Tennis',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinute(),
            'duration_seconds' => 60,
            'started_by_user_id' => $studentUser->id,
        ]);

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'command-device',
            'label' => 'Command Device',
            'platform' => 'windows',
            'last_ipv4' => '192.168.11.60',
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
