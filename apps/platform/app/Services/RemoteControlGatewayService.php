<?php

namespace App\Services;

use App\Models\RemoteControlSession;
use App\Models\StudentDevice;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RemoteControlGatewayService
{
    public function startSession(RemoteControlSession $session, StudentDevice $device): array
    {
        $response = Http::timeout(15)->post(
            rtrim((string) config('services.remote_control.gateway_url'), '/').'/api/sessions/start',
            [
                'session_token' => $session->session_token,
                'target_host' => $device->last_ipv4,
                'username' => $device->remote_access_username,
                'password' => $device->remote_access_password,
                'label' => $device->label,
            ],
        );

        if (! $response->successful()) {
            throw new RuntimeException('Gateway start failed with status '.$response->status().'.');
        }

        return $response->json();
    }

    public function stopSession(RemoteControlSession $session): void
    {
        Http::timeout(10)->post(
            rtrim((string) config('services.remote_control.gateway_url'), '/').'/api/sessions/stop',
            [
                'session_token' => $session->session_token,
            ],
        );
    }
}
