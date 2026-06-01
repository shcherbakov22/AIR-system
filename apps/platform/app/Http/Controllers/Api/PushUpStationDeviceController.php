<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushUpSession;
use App\Services\PushUpSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushUpStationDeviceController extends Controller
{
    protected function authorizeStation(Request $request): void
    {
        $expected = (string) config('services.push_up_station.shared_token', '');
        $provided = (string) $request->header('X-Push-Up-Station-Token', '');

        if ($provided === '' && str_starts_with((string) $request->header('Authorization', ''), 'Bearer ')) {
            $provided = substr((string) $request->header('Authorization'), 7);
        }

        abort_if($expected === '', 503, 'Push-up station API token is not configured.');
        abort_unless(hash_equals($expected, $provided), 401);
    }

    protected function sessionPayload(?PushUpSession $session, PushUpSessionService $service): ?array
    {
        if (! $session) {
            return null;
        }

        $payload = $service->toPayload($session);

        return [
            'id' => $payload['id'],
            'status' => $payload['status'],
            'required_push_ups' => $payload['required_push_ups'],
            'current_rep' => $payload['current_rep'],
            'current_set' => $payload['current_set'],
            'config_reps' => (int) ($payload['configuration']['reps'] ?? $payload['required_push_ups']),
            'config_sets' => (int) ($payload['configuration']['sets'] ?? 1),
            'config_drop_threshold' => (int) ($payload['configuration']['drop_threshold'] ?? 20),
            'config_up_gap' => (int) ($payload['configuration']['up_gap'] ?? 6),
            'config_down_tolerance' => (int) ($payload['configuration']['down_tolerance'] ?? 3),
            'student_name' => $payload['student']['display_name'],
        ];
    }

    public function heartbeat(Request $request, PushUpSessionService $service): JsonResponse
    {
        $this->authorizeStation($request);

        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
            'station_name' => ['nullable', 'string', 'max:120'],
        ]);

        $station = $service->heartbeat(
            $validated['station_key'],
            $validated['station_name'] ?? null,
            null,
        );

        return response()->json([
            'accepted' => true,
            'pending_count' => $service->pendingCount(),
            'session' => $this->sessionPayload($service->currentSessionForStation($station), $service),
        ]);
    }

    public function claimNext(Request $request, PushUpSessionService $service): JsonResponse
    {
        $this->authorizeStation($request);

        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
            'station_name' => ['nullable', 'string', 'max:120'],
        ]);

        $station = $service->heartbeat(
            $validated['station_key'],
            $validated['station_name'] ?? null,
            null,
        );

        return response()->json([
            'accepted' => true,
            'pending_count' => $service->pendingCount(),
            'session' => $this->sessionPayload($service->claimNext($station), $service),
        ]);
    }

    public function start(Request $request, PushUpSession $pushUpSession, PushUpSessionService $service): JsonResponse
    {
        $this->authorizeStation($request);

        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
        ]);

        abort_unless($pushUpSession->station?->station_key === $validated['station_key'], 403);

        return response()->json([
            'accepted' => true,
            'session' => $this->sessionPayload($service->start($pushUpSession), $service),
        ]);
    }

    public function progress(Request $request, PushUpSession $pushUpSession, PushUpSessionService $service): JsonResponse
    {
        $this->authorizeStation($request);

        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
            'current_rep' => ['required', 'integer', 'min:0'],
            'current_set' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_unless($pushUpSession->station?->station_key === $validated['station_key'], 403);

        return response()->json([
            'accepted' => true,
            'session' => $this->sessionPayload($service->progress(
                $pushUpSession,
                (int) $validated['current_rep'],
                isset($validated['current_set']) ? (int) $validated['current_set'] : null,
            ), $service),
        ]);
    }

    public function complete(Request $request, PushUpSession $pushUpSession, PushUpSessionService $service): JsonResponse
    {
        $this->authorizeStation($request);

        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
        ]);

        abort_unless($pushUpSession->station?->station_key === $validated['station_key'], 403);

        return response()->json([
            'accepted' => true,
            'session' => $this->sessionPayload($service->complete($pushUpSession, null), $service),
        ]);
    }

    public function fail(Request $request, PushUpSession $pushUpSession, PushUpSessionService $service): JsonResponse
    {
        $this->authorizeStation($request);

        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        abort_unless($pushUpSession->station?->station_key === $validated['station_key'], 403);

        return response()->json([
            'accepted' => true,
            'session' => $this->sessionPayload($service->fail($pushUpSession, $validated['notes'] ?? null), $service),
        ]);
    }
}
