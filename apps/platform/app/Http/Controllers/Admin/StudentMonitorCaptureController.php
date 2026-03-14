<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentMonitorCapture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentMonitorCaptureController extends Controller
{
    public function dayHistory(StudentMonitorCapture $studentMonitorCapture): JsonResponse
    {
        $anchor = $studentMonitorCapture->captured_at ?? $studentMonitorCapture->uploaded_at ?? Carbon::now();
        $dayStart = $anchor->copy()->startOfDay();
        $dayEnd = $anchor->copy()->endOfDay();

        $captures = StudentMonitorCapture::query()
            ->where('student_id', $studentMonitorCapture->student_id)
            ->where('capture_kind', $studentMonitorCapture->capture_kind)
            ->whereBetween('captured_at', [$dayStart, $dayEnd])
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StudentMonitorCapture $capture) => [
                'id' => $capture->id,
                'capture_kind' => $capture->capture_kind,
                'captured_at' => $capture->captured_at?->toIso8601String(),
                'captured_at_label' => $capture->captured_at?->format('d M, H:i'),
                'uploaded_at' => $capture->uploaded_at?->toIso8601String(),
                'uploaded_at_label' => $capture->uploaded_at?->format('d M, H:i'),
                'task_title' => $capture->task_title_snapshot,
                'source_label' => $capture->source_label,
                'image_url' => route('admin.student-monitor-captures.show', $capture),
            ])
            ->values();

        return response()->json([
            'captures' => $captures,
        ]);
    }

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
