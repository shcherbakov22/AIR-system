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
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class LegacyCaptureController extends Controller
{
    public function check(Request $request)
    {
        $this->authenticateStudent($request);

        // The legacy uploader used this endpoint to fetch a "delays" pair,
        // not the current task's remaining time. Keep the old format so the
        // client continues to schedule uploads normally.
        return response('30:30:OK', 200)
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
        $student = $this->resolveStudentForUpload($request);
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

        $isValidPassword = false;

        if ($user) {
            try {
                $isValidPassword = Hash::check($password, $user->password);
            } catch (RuntimeException) {
                $isValidPassword = hash_equals((string) $user->password, $password);
            }
        }

        if (! $user || ! $isValidPassword) {
            abort(403, 'Invalid uploader credentials.');
        }

        return Student::query()
            ->with('user')
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    protected function resolveStudentForUpload(Request $request): Student
    {
        $username = trim((string) ($request->input('username', $request->input('name'))));

        if ($username === '') {
            throw ValidationException::withMessages([
                'username' => 'Legacy uploader credentials are required.',
            ]);
        }

        $password = (string) ($request->input('pass', $request->input('password')));

        if ($password !== '') {
            return $this->authenticateStudent($request);
        }

        return Student::query()
            ->with('user')
            ->whereHas('user', function ($query) use ($username) {
                $query
                    ->where('username', $username)
                    ->where('role', UserRole::Student->value)
                    ->where('is_active', true);
            })
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
}
