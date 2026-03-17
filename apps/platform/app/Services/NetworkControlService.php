<?php

namespace App\Services;

use App\Models\StudentDevice;
use Illuminate\Support\Facades\Http;

class NetworkControlService
{
    public function sync(StudentDevice $device, array $internetPolicy): array
    {
        if (! (bool) config('services.network_control.enabled', false)) {
            return [
                'status' => 'disabled',
                'reason' => 'network_control_disabled',
                'policy' => $internetPolicy,
            ];
        }

        if ((bool) config('services.network_control.local_gateway_enabled', false)) {
            return [
                'status' => 'pending_local_sync',
                'reason' => 'local_gateway_sync_command',
                'policy' => $internetPolicy,
            ];
        }

        $baseUrl = rtrim((string) config('services.network_control.base_url'), '/');

        if ($baseUrl === '') {
            return [
                'status' => 'skipped',
                'reason' => 'network_control_not_configured',
                'policy' => $internetPolicy,
            ];
        }

        $response = Http::acceptJson()
            ->withToken((string) config('services.network_control.token'))
            ->post($baseUrl.'/policies/sync', [
                'device_key' => $device->device_key,
                'student_id' => $device->student_id,
                'hostname' => $device->hostname,
                'label' => $device->label,
                'internet_policy' => $internetPolicy,
            ]);

        return [
            'status' => $response->successful() ? 'synced' : 'failed',
            'http_status' => $response->status(),
            'body' => $response->json(),
            'policy' => $internetPolicy,
        ];
    }
}
