<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\AttentionTrackingViolationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttentionEventController extends Controller
{
    public function store(
        Request $request,
        AttentionTrackingViolationService $attentionTrackingViolationService,
    ): JsonResponse {
        $student = $request->user()?->student;
        abort_unless($student !== null, 403);

        $payload = $request->validate([
            'event_type' => ['required', 'string', 'in:look_away'],
            'occurred_at' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
            'payload.reason' => ['nullable', 'string', 'max:120'],
            'payload.score' => ['nullable', 'numeric'],
            'payload.away_seconds' => ['nullable', 'numeric', 'min:0'],
            'payload.client_event_id' => ['nullable', 'string', 'max:120'],
        ]);

        $clientEventId = $payload['payload']['client_event_id'] ?? null;
        if (is_string($clientEventId) && $clientEventId !== '') {
            $cacheKey = sprintf('attention-event:%d:%s', $student->getKey(), $clientEventId);
            if (! Cache::add($cacheKey, true, now()->addDay())) {
                return response()->json([
                    'accepted' => true,
                    'reason' => 'duplicate_client_event',
                ]);
            }
        }

        $result = $attentionTrackingViolationService->recordLookAwayEventForStudent(
            $student,
            $request->filled('occurred_at') ? $request->date('occurred_at') : now(),
            $payload['payload'] ?? [],
        );

        return response()->json($result);
    }
}
