<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpeechAnnouncement;
use App\Services\SpeechAnnouncementPlaybackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $afterId = request()->integer('after_id');

        $playbackService = app(SpeechAnnouncementPlaybackService::class);
        $playbackService->releaseExpiredClaims();

        $announcement = \Illuminate\Support\Facades\DB::transaction(function () use ($afterId) {
            $announcement = SpeechAnnouncement::query()
                ->whereNull('spoken_at')
                ->whereNull('processing_started_at')
                ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if (! $announcement) {
                return null;
            }

            $announcement->update([
                'processing_started_at' => now(),
                'processing_host' => (string) gethostname(),
            ]);

            return $announcement->fresh();
        });

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

    public function latestPending(): JsonResponse
    {
        return response()->json([
            'latest_pending_id' => SpeechAnnouncement::query()
                ->whereNull('spoken_at')
                ->max('id'),
        ]);
    }

    public function markSpoken(SpeechAnnouncement $speechAnnouncement, SpeechAnnouncementPlaybackService $playbackService): JsonResponse
    {
        $playbackService->markAnnouncementSpoken($speechAnnouncement);

        return response()->json([
            'accepted' => true,
            'pending_count' => SpeechAnnouncement::query()->whereNull('spoken_at')->count(),
        ]);
    }

    public function updateState(Request $request, SpeechAnnouncementPlaybackService $playbackService): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $playbackService->setEnabled((bool) $validated['enabled']);

        return response()->json([
            'enabled' => $playbackService->isEnabled(),
            'pending_count' => SpeechAnnouncement::query()->whereNull('spoken_at')->count(),
        ]);
    }
}
