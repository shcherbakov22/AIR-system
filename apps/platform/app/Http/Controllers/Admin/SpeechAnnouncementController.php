<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpeechAnnouncement;
use Illuminate\Http\JsonResponse;
class SpeechAnnouncementController extends Controller
{
    public function history(): JsonResponse
    {
        return response()->json([
            'announcements' => SpeechAnnouncement::query()
                ->with('student')
                ->whereNotNull('spoken_at')
                ->latest('spoken_at')
                ->limit(100)
                ->get()
                ->map(fn (SpeechAnnouncement $announcement) => [
                    'id' => $announcement->id,
                    'kind' => $announcement->kind,
                    'message' => $announcement->message,
                    'spoken_at' => $announcement->spoken_at?->toIso8601String(),
                    'spoken_at_label' => $announcement->spoken_at?->format('j M, H:i:s'),
                    'student_name' => $announcement->student?->display_name,
                ])
                ->values(),
        ]);
    }

    public function next(): JsonResponse
    {
        $announcement = SpeechAnnouncement::query()
            ->whereNull('spoken_at')
            ->oldest('id')
            ->first();

        if (! $announcement) {
            return response()->json([
                'announcement' => null,
            ]);
        }

        return response()->json([
            'announcement' => [
                'id' => $announcement->id,
                'kind' => $announcement->kind,
                'message' => $announcement->message,
                'processing_started_at' => $announcement->processing_started_at?->toIso8601String(),
                'processing_host' => $announcement->processing_host,
            ],
        ]);
    }
}
