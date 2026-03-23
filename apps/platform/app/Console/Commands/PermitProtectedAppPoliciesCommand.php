<?php

namespace App\Console\Commands;

use App\Models\StudentAppPolicy;
use App\Services\StudentAppPolicyService;
use Illuminate\Console\Command;

class PermitProtectedAppPoliciesCommand extends Command
{
    protected $signature = 'companion:permit-protected-app-policies';

    protected $description = 'Reset protected shell app policies back to permitted.';

    public function handle(): int
    {
        $protected = [
            'explorer.exe',
            'rundll32.exe',
            'shellexperiencehost.exe',
            'startmenuexperiencehost.exe',
            'searchhost.exe',
            'searchapp.exe',
            'dwm.exe',
            'taskmgr.exe',
        ];

        $updated = StudentAppPolicy::query()
            ->whereIn('app_key', $protected)
            ->update([
                'status' => StudentAppPolicyService::STATUS_PERMITTED,
                'grace_deadline_at' => null,
            ]);

        $this->info("Updated {$updated} protected app policies.");

        return self::SUCCESS;
    }
}
