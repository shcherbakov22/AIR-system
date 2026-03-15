<?php

namespace App\Services;

use App\Models\StudentDevice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class GatewayPolicyService
{
    public function __construct(
        private readonly DevicePolicyService $devicePolicyService,
    ) {
    }

    public function managedDevices(): Collection
    {
        return StudentDevice::query()
            ->with(['student.user', 'student.violations', 'student.scheduleRuns.blocks.taskTemplate', 'student.taskSessions.taskTemplate'])
            ->whereNull('revoked_at')
            ->whereNotNull('last_ipv4')
            ->orderBy('id')
            ->get();
    }

    public function buildRuleset(): string
    {
        $tableName = (string) config('services.network_control.gateway_table_name', 'air_companion');
        $serverIp = (string) config('services.network_control.gateway_server_ipv4', '192.168.11.228');
        $dnsIp = (string) config('services.network_control.gateway_dns_ipv4', $serverIp);

        $lines = [
            "table inet {$tableName} {",
            "    chain forward {",
            "        type filter hook forward priority -10; policy accept;",
        ];

        foreach ($this->managedDevices() as $device) {
            $policy = $this->devicePolicyService->buildForDevice($device);
            $ip = $device->last_ipv4;

            if (! is_string($ip) || $ip === '') {
                continue;
            }

            if (($policy['internet_policy']['internet_allowed'] ?? false) === false) {
                $commentBase = $this->nftComment("device {$device->device_key}");
                $lines[] = "        ip saddr {$ip} ip daddr {$serverIp} tcp dport { 80, 443 } accept comment \"{$commentBase} air\"";
                $lines[] = "        ip saddr {$ip} ip daddr {$dnsIp} udp dport 53 accept comment \"{$commentBase} dns-udp\"";
                $lines[] = "        ip saddr {$ip} ip daddr {$dnsIp} tcp dport 53 accept comment \"{$commentBase} dns-tcp\"";
                $lines[] = "        ip saddr {$ip} drop comment \"{$commentBase} block\"";
            }
        }

        $lines[] = '    }';
        $lines[] = '}';

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    public function applyRuleset(): array
    {
        $binary = (string) config('services.network_control.gateway_nft_binary', 'nft');
        $ruleset = $this->buildRuleset();
        $tableName = (string) config('services.network_control.gateway_table_name', 'air_companion');

        $flushProcess = Process::timeout(15)->run([$binary, 'delete', 'table', 'inet', $tableName]);

        if ($flushProcess->failed() && ! str_contains(strtolower($flushProcess->errorOutput()), 'no such file or directory')) {
            throw new RuntimeException(trim($flushProcess->errorOutput() ?: $flushProcess->output()) ?: 'Failed to clear existing gateway table.');
        }

        $applyProcess = Process::input($ruleset)->timeout(15)->run([$binary, '-f', '-']);

        if ($applyProcess->failed()) {
            throw new RuntimeException(trim($applyProcess->errorOutput() ?: $applyProcess->output()) ?: 'Failed to apply gateway rules.');
        }

        return [
            'managed_devices' => $this->managedDevices()->count(),
            'ruleset' => $ruleset,
        ];
    }

    protected function nftComment(string $value): string
    {
        return str_replace('"', '', $value);
    }
}
