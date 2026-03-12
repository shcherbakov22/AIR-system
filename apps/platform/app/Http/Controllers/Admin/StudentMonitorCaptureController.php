<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentMonitorCapture;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentMonitorCaptureController extends Controller
{
    public function show(StudentMonitorCapture $studentMonitorCapture): Response|BinaryFileResponse
    {
        $disk = Storage::disk($studentMonitorCapture->disk);

        abort_unless($disk->exists($studentMonitorCapture->path), 404);

        return response()->file($disk->path($studentMonitorCapture->path), [
            'Content-Type' => $studentMonitorCapture->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
