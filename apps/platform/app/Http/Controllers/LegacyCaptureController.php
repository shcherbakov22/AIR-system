<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentMonitorCapture;
use App\Models\TaskSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LegacyCaptureController extends Controller
{
    public function check(Request $request)
    {
        $student = $this->authenticateStudent($request);

        $taskSession = $this->latestTaskSession($student);
        $remainingSeconds = 30 * 60 + 30;

        if ($taskSession && $taskSession->planned_duration_minutes) {
            $elapsedSeconds = max(
                $taskSession->duration_seconds ?? 0,
                ($taskSession->duration_seconds ?? 0) + now()->diffInSeconds($taskSession->started_at),
            );

            $remainingSeconds = max(($taskSession->planned_duration_minutes * 60) - $elapsedSeconds, 0);
        }

        return response($this->formatLegacyClock($remainingSeconds).':OK', 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function storeScreen(Request $request)
    {
        return $this->storeCapture($request, 'screen');
    }

    public function storeCamera(Request $request)
    {
        return $this->storeCapture($request, 'camera');
    }

    protected function storeCapture(Request $request, string $captureKind)
    {
        $student = $this->authenticateStudent($request);
        $upload = $this->resolveUpload($request);
        $taskSession = $this->latestTaskSession($student);
        $capturedAt = now();
        $extension = strtolower($upload->getClientOriginalExtension() ?: $upload->extension() ?: 'jpg');

        $path = $upload->storeAs(
            'student-monitor-captures/'.$student->user->username.'/'.$captureKind.'/'.$capturedAt->format('Y/m/d'),
            Str::uuid().'.'.$extension,
            'local',
        );

        StudentMonitorCapture::create([
            'student_id' => $student->id,
            'edge_client_id' => null,
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
            'source_label' => 'Legacy uploader',
            'source_version' => 'legacy-compat',
            'meta' => [
                'legacy_compat' => true,
                'remote_addr' => $request->ip(),
            ],
        ]);

        return response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    protected function authenticateStudent(Request $request): Student
    {
        $username = trim((string) ($request->input('username', $request->input('name'))));
        $password = (string) ($request->input('pass', $request->input('password')));

        if ($username === '' || $password === '') {
            throw ValidationException::withMessages([
                'username' => 'Legacy uploader credentials are required.',
            ]);
        }

        $user = User::query()
            ->where('username', $username)
            ->where('role', UserRole::Student->value)
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            abort(403, 'Invalid uploader credentials.');
        }

        return Student::query()
            ->with('user')
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    protected function latestTaskSession(Student $student): ?TaskSession
    {
        return TaskSession::query()
            ->where('student_id', $student->id)
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->latest('started_at')
            ->latest('id')
            ->first();
    }

    protected function resolveUpload(Request $request): UploadedFile
    {
        $upload = $request->file('capture')
            ?? $request->file('image')
            ?? $request->file('filename');

        if (! $upload instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'filename' => 'An image upload is required.',
            ]);
        }

        return $upload;
    }

    protected function formatLegacyClock(int $seconds): string
    {
        $safeSeconds = max(0, $seconds);
        $minutes = intdiv($safeSeconds, 60);
        $remainingSeconds = $safeSeconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }
}
