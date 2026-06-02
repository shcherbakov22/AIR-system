<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushUpStation;
use App\Models\PushUpSession;
use App\Services\PushUpSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            'config_drop_threshold' => (int) ($payload['configuration']['drop_threshold'] ?? 22),
            'config_up_gap' => (int) ($payload['configuration']['up_gap'] ?? 6),
            'config_down_tolerance' => (int) ($payload['configuration']['down_tolerance'] ?? 3),
            'student_name' => $payload['student']['display_name'],
        ];
    }

    protected function firmwareManifestPayload(): array
    {
        $path = (string) config('services.push_up_station.firmware_path');
        $enabled = (bool) config('services.push_up_station.firmware_updates_enabled', true);
        $available = $enabled && is_file($path);

        return [
            'enabled' => $enabled,
            'available' => $available,
            'version' => (string) config('services.push_up_station.firmware_version', ''),
            'download_path' => route('api.push-up-station.firmware.download', [], false),
            'size' => $available ? filesize($path) : 0,
            'sha256' => $available ? hash_file('sha256', $path) : null,
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

    public function updateManifest(Request $request): JsonResponse
    {
        $this->authorizeStation($request);

        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
            'firmware_version' => ['nullable', 'string', 'max:60'],
        ]);

        Log::info('Push-up station firmware manifest requested.', [
            'station_key' => $validated['station_key'],
            'firmware_version' => $validated['firmware_version'] ?? null,
            'manifest' => $this->firmwareManifestPayload(),
        ]);

        return response()->json([
            'accepted' => true,
            'firmware' => $this->firmwareManifestPayload(),
        ]);
    }

    public function downloadFirmware(Request $request): BinaryFileResponse
    {
        $this->authorizeStation($request);

        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
        ]);

        $manifest = $this->firmwareManifestPayload();
        abort_unless($manifest['enabled'] && $manifest['available'], 404);

        Log::info('Push-up station firmware download started.', [
            'station_key' => $validated['station_key'],
            'version' => $manifest['version'],
            'size' => $manifest['size'],
            'sha256' => $manifest['sha256'],
        ]);

        return response()->file((string) config('services.push_up_station.firmware_path'), [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) $manifest['size'],
            'X-Firmware-Version' => $manifest['version'],
            'X-Firmware-SHA256' => $manifest['sha256'],
        ]);
    }

    public function debug(Request $request, PushUpSessionService $service): JsonResponse
    {
        $this->authorizeStation($request);

        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
        ]);

        $station = PushUpStation::query()
            ->where('station_key', $validated['station_key'])
            ->first();

        return response()->json([
            'accepted' => true,
            'station' => $station ? [
                'id' => $station->id,
                'station_key' => $station->station_key,
                'name' => $station->name,
                'last_seen_at' => $station->last_seen_at?->toAtomString(),
                'last_claimed_at' => $station->last_claimed_at?->toAtomString(),
            ] : null,
            'pending_count' => $service->pendingCount(),
            'session' => $station ? $this->sessionPayload($service->currentSessionForStation($station), $service) : null,
            'firmware' => $this->firmwareManifestPayload(),
        ]);
    }

    public function storeLog(Request $request): JsonResponse
    {
        $this->authorizeStation($request);

        $validated = $request->validate([
            'station_key' => ['required', 'string', 'max:120'],
            'level' => ['nullable', 'string', 'in:debug,info,warning,error'],
            'event' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
            'firmware_version' => ['nullable', 'string', 'max:60'],
            'state' => ['nullable', 'string', 'max:80'],
            'ip_address' => ['nullable', 'string', 'max:80'],
            'free_heap' => ['nullable', 'integer', 'min:0'],
            'distance' => ['nullable', 'integer'],
            'context' => ['nullable', 'array'],
        ]);

        $level = $validated['level'] ?? 'info';
        $context = $validated['context'] ?? [];
        $context['station_key'] = $validated['station_key'];
        $context['event'] = $validated['event'];
        $context['firmware_version'] = $validated['firmware_version'] ?? null;
        $context['state'] = $validated['state'] ?? null;
        $context['ip_address'] = $validated['ip_address'] ?? null;
        $context['free_heap'] = $validated['free_heap'] ?? null;
        $context['distance'] = $validated['distance'] ?? null;

        Log::log($level, $validated['message'] ?? 'Push-up station event.', $context);

        return response()->json(['accepted' => true]);
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
