<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreStudentMonitorCaptureRequest;
use App\Models\EdgeClient;
use App\Models\Student;
use App\Models\StudentMonitorCapture;
use App\Models\TaskSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentMonitorCaptureController extends Controller
{
    public function storeCamera(StoreStudentMonitorCaptureRequest $request): JsonResponse
    {
        return $this->storeCapture($request, 'camera');
    }

    public function storeScreen(StoreStudentMonitorCaptureRequest $request): JsonResponse
    {
        return $this->storeCapture($request, 'screen');
    }

    protected function storeCapture(StoreStudentMonitorCaptureRequest $request, string $captureKind): JsonResponse
    {
        $student = Student::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('username', $request->string('username')->toString()))
            ->firstOrFail();

        $capture = DB::transaction(function () use ($request, $student, $captureKind) {
            $edgeClient = $this->upsertEdgeClient($request);
            $upload = $this->resolveUpload($request);
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

            return StudentMonitorCapture::create([
                'student_id' => $student->id,
                'edge_client_id' => $edgeClient?->id,
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
                'source_label' => $request->input('label') ?: $edgeClient?->label,
                'source_version' => $request->input('version') ?: $edgeClient?->version,
                'meta' => $request->input('meta', []),
            ]);
        });

        return response()->json([
            'accepted' => true,
            'capture' => [
                'id' => $capture->id,
                'capture_kind' => $capture->capture_kind,
                'captured_at' => $capture->captured_at?->toAtomString(),
                'task_title' => $capture->task_title_snapshot,
                'student' => [
                    'id' => $student->id,
                    'display_name' => $student->display_name,
                    'username' => $student->user->username,
                ],
            ],
            'server_now' => now()->toAtomString(),
        ]);
    }

    protected function resolveUpload(StoreStudentMonitorCaptureRequest $request): UploadedFile
    {
        return $request->file('capture')
            ?? $request->file('image')
            ?? $request->file('filename');
    }

    protected function upsertEdgeClient(StoreStudentMonitorCaptureRequest $request): ?EdgeClient
    {
        if (! $request->filled('client_key')) {
            return null;
        }

        $captureCapability = $request->route()->getActionMethod() === 'storeCamera'
            ? 'camera_capture'
            : 'screen_capture';

        $captureKind = $request->route()->getActionMethod() === 'storeCamera'
            ? 'camera'
            : 'screen';

        return EdgeClient::query()->updateOrCreate(
            ['client_key' => $request->string('client_key')->toString()],
            [
                'client_type' => $request->string('client_type')->toString(),
                'label' => $request->string('label')->toString(),
                'version' => $request->input('version'),
                'capabilities' => [$captureCapability],
                'last_seen_at' => now(),
                'last_seen_ip' => $request->ip(),
                'last_user_agent' => $request->userAgent(),
                'last_payload' => [
                    'username' => $request->string('username')->toString(),
                    'meta' => $request->input('meta', []),
                    'capture_kind' => $captureKind,
                ],
            ],
        );
    }
}
