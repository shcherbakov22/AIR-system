<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCompanionAttentionEventRequest;
use App\Services\AttentionTrackingViolationService;
use Illuminate\Http\JsonResponse;

class CompanionAttentionEventController extends Controller
{
    public function store(
        StoreCompanionAttentionEventRequest $request,
        AttentionTrackingViolationService $attentionTrackingViolationService,
    ): JsonResponse {
        $device = $request->device();

        $result = $attentionTrackingViolationService->recordLookAwayEvent(
            $device,
            $request->filled('occurred_at') ? $request->date('occurred_at') : now(),
            $request->input('payload', []),
            $request->string('event_type')->toString(),
        );

        return response()->json($result);
    }
}
