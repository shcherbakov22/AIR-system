<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompanionDeviceRequest;
use App\Http\Requests\Api\StartAttentionCalibrationSessionRequest;
use App\Http\Requests\Api\StoreAttentionCalibrationBatchRequest;
use App\Models\DeviceAttentionCalibrationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CompanionAttentionCalibrationController extends Controller
{
    public function status(CompanionDeviceRequest $request): JsonResponse
    {
        $device = $request->device();
        $latestSession = $device->attentionCalibrationSessions()->latest('id')->first();

        return response()->json([
            'accepted' => true,
            'device_id' => $device->id,
            'latest_session' => $latestSession ? $this->serializeSession($latestSession) : null,
            'model' => $latestSession ? [
                'provider' => $latestSession->provider,
                'status' => $latestSession->status,
                'version' => $latestSession->model_version,
                'ready_at' => $latestSession->model_ready_at?->toAtomString(),
            ] : null,
        ]);
    }

    public function start(StartAttentionCalibrationSessionRequest $request): JsonResponse
    {
        $device = $request->device();

        $device->attentionCalibrationSessions()
            ->whereIn('status', ['collecting', 'queued'])
            ->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

        $session = $device->attentionCalibrationSessions()->create([
            'session_uuid' => (string) Str::uuid(),
            'provider' => $request->string('provider')->toString(),
            'status' => 'collecting',
            'sample_count' => 0,
            'started_at' => now(),
            'meta' => $request->input('meta', []),
        ]);

        return response()->json([
            'accepted' => true,
            'session' => $this->serializeSession($session),
        ]);
    }

    public function storeBatch(StoreAttentionCalibrationBatchRequest $request, string $sessionUuid): JsonResponse
    {
        $device = $request->device();
        $session = $device->attentionCalibrationSessions()
            ->where('session_uuid', $sessionUuid)
            ->firstOrFail();

        abort_unless(in_array($session->status, ['collecting', 'queued'], true), 409);

        $samples = $request->input('samples', []);
        $sampleCount = count($samples);

        $session->batches()->create([
            'sequence_number' => (int) $session->batches()->count() + 1,
            'sample_count' => $sampleCount,
            'captured_at' => $request->filled('captured_at') ? $request->date('captured_at') : now(),
            'payload' => [
                'samples' => $samples,
                'meta' => $request->input('meta', []),
            ],
        ]);

        $session->forceFill([
            'sample_count' => $session->sample_count + $sampleCount,
            'status' => $request->boolean('finalize') ? 'queued' : $session->status,
            'completed_at' => $request->boolean('finalize') ? now() : $session->completed_at,
            'training_requested_at' => $request->boolean('finalize') ? now() : $session->training_requested_at,
        ])->save();

        return response()->json([
            'accepted' => true,
            'session' => $this->serializeSession($session->fresh()),
        ]);
    }

    private function serializeSession(DeviceAttentionCalibrationSession $session): array
    {
        return [
            'id' => $session->id,
            'session_uuid' => $session->session_uuid,
            'provider' => $session->provider,
            'status' => $session->status,
            'sample_count' => $session->sample_count,
            'started_at' => $session->started_at?->toAtomString(),
            'completed_at' => $session->completed_at?->toAtomString(),
            'training_requested_at' => $session->training_requested_at?->toAtomString(),
            'model_ready_at' => $session->model_ready_at?->toAtomString(),
            'model_version' => $session->model_version,
        ];
    }
}
