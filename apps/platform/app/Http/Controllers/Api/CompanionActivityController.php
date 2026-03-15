<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCompanionActivityRequest;
use Illuminate\Http\JsonResponse;

class CompanionActivityController extends Controller
{
    public function store(StoreCompanionActivityRequest $request): JsonResponse
    {
        $device = $request->device();

        $event = $device->activityEvents()->create([
            'event_type' => $request->string('event_type')->toString(),
            'app_name' => $request->input('app_name'),
            'window_title' => $request->input('window_title'),
            'browser_domain' => $request->input('browser_domain'),
            'payload' => $request->input('payload', []),
            'observed_at' => now(),
        ]);

        $device->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'accepted' => true,
            'event' => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'observed_at' => $event->observed_at?->toAtomString(),
            ],
        ]);
    }
}
