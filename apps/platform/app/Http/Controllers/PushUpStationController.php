<?php

namespace App\Http\Controllers;

use App\Models\PushUpSession;
use App\Services\PushUpSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushUpStationController extends Controller
{
    public function heartbeat(Request $request, PushUpSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
            'station_name' => ['nullable', 'string', 'max:120'],
        ]);

        $station = $service->heartbeat(
            $validated['station_key'],
            $validated['station_name'] ?? null,
            $request->user(),
        );

        return response()->json([
            'accepted' => true,
            'station' => [
                'id' => $station->id,
                'name' => $station->name,
                'last_seen_at' => $station->last_seen_at?->toAtomString(),
            ],
            'pending_count' => $service->pendingCount(),
            'current_session' => ($currentSession = $service->currentSessionForStation($station))
                ? $service->toPayload($currentSession)
                : null,
        ]);
    }

    public function claimNext(Request $request, PushUpSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
            'station_name' => ['nullable', 'string', 'max:120'],
        ]);

        $station = $service->heartbeat(
            $validated['station_key'],
            $validated['station_name'] ?? null,
            $request->user(),
        );

        $session = $service->claimNext($station);

        return response()->json([
            'accepted' => true,
            'session' => $session ? $service->toPayload($session) : null,
            'pending_count' => $service->pendingCount(),
        ]);
    }

    public function start(Request $request, PushUpSession $pushUpSession, PushUpSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
        ]);

        abort_unless($pushUpSession->station?->station_key === $validated['station_key'], 403);

        return response()->json([
            'accepted' => true,
            'session' => $service->toPayload($service->start($pushUpSession)),
        ]);
    }

    public function progress(Request $request, PushUpSession $pushUpSession, PushUpSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
            'current_rep' => ['required', 'integer', 'min:0'],
            'current_set' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_unless($pushUpSession->station?->station_key === $validated['station_key'], 403);

        return response()->json([
            'accepted' => true,
            'session' => $service->toPayload($service->progress(
                $pushUpSession,
                (int) $validated['current_rep'],
                isset($validated['current_set']) ? (int) $validated['current_set'] : null,
            )),
        ]);
    }

    public function complete(Request $request, PushUpSession $pushUpSession, PushUpSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
        ]);

        abort_unless($pushUpSession->station?->station_key === $validated['station_key'], 403);

        return response()->json([
            'accepted' => true,
            'session' => $service->toPayload($service->complete($pushUpSession, $request->user())),
        ]);
    }

    public function fail(Request $request, PushUpSession $pushUpSession, PushUpSessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        abort_unless($pushUpSession->station?->station_key === $validated['station_key'], 403);

        return response()->json([
            'accepted' => true,
            'session' => $service->toPayload($service->fail($pushUpSession, $validated['notes'] ?? null)),
        ]);
    }
}
