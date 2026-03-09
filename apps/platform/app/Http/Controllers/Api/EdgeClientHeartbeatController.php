<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEdgeClientHeartbeatRequest;
use App\Models\EdgeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EdgeClientHeartbeatController extends Controller
{
    public function store(StoreEdgeClientHeartbeatRequest $request): JsonResponse
    {
        $edgeClient = DB::transaction(function () use ($request) {
            $payload = $request->validated();

            $edgeClient = EdgeClient::query()->updateOrCreate(
                ['client_key' => $payload['client_key']],
                [
                    'client_type' => $payload['client_type'],
                    'label' => $payload['label'],
                    'version' => $payload['version'] ?? null,
                    'capabilities' => $payload['capabilities'] ?? [],
                    'last_seen_at' => now(),
                    'last_seen_ip' => $request->ip(),
                    'last_user_agent' => $request->userAgent(),
                    'last_payload' => $payload['meta'] ?? [],
                ],
            );

            $edgeClient->heartbeats()->create([
                'received_at' => now(),
                'ip_address' => $request->ip(),
                'payload' => [
                    'client_key' => $payload['client_key'],
                    'client_type' => $payload['client_type'],
                    'label' => $payload['label'],
                    'version' => $payload['version'] ?? null,
                    'capabilities' => $payload['capabilities'] ?? [],
                    'meta' => $payload['meta'] ?? [],
                ],
            ]);

            return $edgeClient->fresh();
        });

        return response()->json([
            'accepted' => true,
            'edge_client' => [
                'id' => $edgeClient->id,
                'client_key' => $edgeClient->client_key,
                'client_type' => $edgeClient->client_type,
                'label' => $edgeClient->label,
                'is_enabled' => $edgeClient->is_enabled,
            ],
            'server_now' => now()->toAtomString(),
            'polling_hint_seconds' => 60,
        ]);
    }
}
