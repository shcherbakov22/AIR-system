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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class LegacyCaptureController extends Controller
{
    public function check(Request $request)
    {
        Log::info('legacy_capture.check', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'query' => $request->query(),
            'all' => $request->except(['filename', 'capture', 'image']),
            'query_string' => $request->server('QUERY_STRING'),
            'content_type' => $request->header('Content-Type'),
        ]);

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
        Log::info('legacy_capture.upload_attempt', [
            'kind' => $captureKind,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'query' => $request->query(),
            'keys' => array_keys($request->except(['filename', 'capture', 'image'])),
            'file_keys' => array_keys($request->allFiles()),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
        ]);

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

        Log::info('legacy_capture.upload_stored', [
            'kind' => $captureKind,
            'student' => $student->user->username,
            'path' => $path,
            'size_bytes' => $upload->getSize(),
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
            $rawBody = $request->getContent();
            $contentType = strtolower((string) $request->header('Content-Type'));

            if ($rawBody !== '' && (str_contains($contentType, 'image/') || str_contains($contentType, 'application/octet-stream'))) {
                $tempPath = storage_path('app/tmp/'.Str::uuid().'.jpg');
                @mkdir(dirname($tempPath), 0777, true);
                file_put_contents($tempPath, $rawBody);

                Log::info('legacy_capture.upload_raw_body', [
                    'content_type' => $contentType,
                    'bytes' => strlen($rawBody),
                ]);

                return new UploadedFile(
                    $tempPath,
                    'legacy-upload.jpg',
                    $request->header('Content-Type') ?: 'image/jpeg',
                    null,
                    true,
                );
            }

            Log::warning('legacy_capture.upload_missing_file', [
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
                'raw_bytes' => strlen((string) $request->getContent()),
                'keys' => array_keys($request->request->all()),
                'file_keys' => array_keys($request->allFiles()),
            ]);

            throw ValidationException::withMessages([
                'filename' => 'An image upload is required.',
            ]);
        }

        return $upload;
    }
}
