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

        $device->forceFill([
            'label' => $request->input('label') ?: $device->label,
            'hostname' => $request->input('hostname') ?: $device->hostname,
            'app_version' => $request->input('app_version') ?: $device->app_version,
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
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
