<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCompanionHeartbeatRequest;
use Illuminate\Http\JsonResponse;

class CompanionHeartbeatController extends Controller
{
    public function store(StoreCompanionHeartbeatRequest $request): JsonResponse
    {
        $device = $request->device();
        $remoteControlReady = $request->has('remote_control_ready')
            ? (bool) $request->boolean('remote_control_ready')
            : $device->remote_control_ready;
        $remoteControlFailureReason = $request->has('remote_control_failure_reason')
            ? $request->input('remote_control_failure_reason')
            : $device->remote_control_failure_reason;

        if ($request->has('remote_control_ready') && $remoteControlReady) {
            $remoteControlFailureReason = null;
        }

        $device->forceFill([
            'label' => $request->input('label') ?: $device->label,
            'hostname' => $request->input('hostname') ?: $device->hostname,
            'app_version' => $request->input('app_version') ?: $device->app_version,
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
            'last_ipv4' => $request->input('ipv4') ?: $device->last_ipv4,
            'last_mac_address' => $request->input('mac_address') ?: $device->last_mac_address,
            'last_gateway_ipv4' => $request->input('gateway_ipv4') ?: $device->last_gateway_ipv4,
            'network_adapter_name' => $request->input('network_adapter_name') ?: $device->network_adapter_name,
            'remote_control_ready' => $remoteControlReady,
            'remote_control_last_checked_at' => $request->has('remote_control_ready') || $request->filled('remote_control_failure_reason')
                ? now()
                : $device->remote_control_last_checked_at,
            'remote_control_failure_reason' => $remoteControlFailureReason,
            'meta' => array_merge($device->meta ?? [], $request->input('meta', [])),
        ])->save();

        $device->heartbeats()->create([
            'received_at' => now(),
            'ip_address' => $request->ip(),
            'payload' => $request->input('meta', []),
        ]);

        return response()->json([
            'accepted' => true,
            'server_now' => now()->toAtomString(),
            'polling_hint_seconds' => 10,
        ]);
    }
}
