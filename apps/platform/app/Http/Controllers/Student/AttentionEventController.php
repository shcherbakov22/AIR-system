<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\AttentionTrackingViolationService;
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
        ]);

        $result = $attentionTrackingViolationService->recordLookAwayEventForStudent(
            $student,
            $request->filled('occurred_at') ? $request->date('occurred_at') : now(),
            $payload['payload'] ?? [],
        );

        return response()->json($result);
    }
}
