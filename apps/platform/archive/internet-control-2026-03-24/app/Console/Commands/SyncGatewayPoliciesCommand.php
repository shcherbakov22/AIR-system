<?php

namespace App\Console\Commands;

use App\Services\GatewayPolicyService;
use Illuminate\Console\Command;
use Throwable;

class SyncGatewayPoliciesCommand extends Command
{
    protected $signature = 'gateway:sync-device-policies {--dry-run : Print the nftables ruleset without applying it}';

    protected $description = 'Sync per-device internet policies to the local nftables gateway ruleset.';

    public function handle(GatewayPolicyService $gatewayPolicyService): int
    {
        if (! (bool) config('services.network_control.enabled', false)) {
            $this->warn('Network control is disabled.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->line($gatewayPolicyService->buildRuleset());

            return self::SUCCESS;
        }

        try {
            $result = $gatewayPolicyService->applyRuleset();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Gateway rules synced for {$result['managed_devices']} devices.");

        return self::SUCCESS;
    }
}
