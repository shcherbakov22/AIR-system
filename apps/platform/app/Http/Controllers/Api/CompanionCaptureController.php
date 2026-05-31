<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCompanionCaptureRequest;
use App\Models\StudentMonitorCapture;
use App\Models\TaskSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RuntimeException;

class CompanionCaptureController extends Controller
{
    public function storeScreen(StoreCompanionCaptureRequest $request): JsonResponse
    {
        return $this->storeCapture($request, 'screen');
    }

    public function storeCamera(StoreCompanionCaptureRequest $request): JsonResponse
    {
        return $this->storeCapture($request, 'camera');
    }

    protected function storeCapture(StoreCompanionCaptureRequest $request, string $captureKind): JsonResponse
    {
        $device = $request->device()->load('student.user');
        $student = $device->student;
        $upload = $request->file('capture');
        $capturedAt = $request->date('captured_at') ?? now();

        $taskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->latest('started_at')
            ->latest('id')
            ->first();

        $extension = strtolower($upload->getClientOriginalExtension() ?: $upload->extension() ?: 'jpg');
        $path = $upload->storeAs(
            'student-monitor-captures/'.$student->user->username.'/'.$captureKind.'/'.$capturedAt->format('Y/m/d'),
            Str::uuid().'.'.$extension,
            'local',
        );

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Failed to store companion '.$captureKind.' capture.');
        }

        $capture = StudentMonitorCapture::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'task_session_id' => $taskSession?->id,
            'schedule_run_id' => $taskSession?->schedule_run_id,
            'capture_kind' => $captureKind,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $upload->getClientMimeType(),
            'size_bytes' => $upload->getSize(),
            'captured_at' => $capturedAt,
            'uploaded_at' => now(),
            'task_title_snapshot' => $taskSession?->task_title_snapshot,
            'app_name_snapshot' => $request->input('app_name'),
            'window_title_snapshot' => $request->input('window_title'),
            'browser_domain_snapshot' => $request->input('browser_domain'),
            'source_label' => $device->label,
            'source_version' => $device->app_version,
            'meta' => $request->input('meta', []),
        ]);

        $device->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'accepted' => true,
            'capture' => [
                'id' => $capture->id,
                'capture_kind' => $capture->capture_kind,
                'captured_at' => $capture->captured_at?->toAtomString(),
            ],
        ]);
    }
}
