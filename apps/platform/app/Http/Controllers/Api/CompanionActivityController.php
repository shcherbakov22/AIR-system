<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCompanionActivityRequest;
use App\Services\StudentAppPolicyService;
use Illuminate\Http\JsonResponse;

class CompanionActivityController extends Controller
{
    public function store(StoreCompanionActivityRequest $request, StudentAppPolicyService $studentAppPolicyService): JsonResponse
    {
        $device = $request->device();
        $eventType = $request->string('event_type')->toString();

        $event = $device->activityEvents()->create([
            'event_type' => $eventType,
            'app_name' => $request->input('app_name'),
            'window_title' => $request->input('window_title'),
            'browser_domain' => $request->input('browser_domain'),
            'payload' => $request->input('payload', []),
            'observed_at' => now(),
        ]);

        if ($eventType === 'open_apps') {
            $studentAppPolicyService->syncOpenApps($device, $request->input('payload.apps', []));
        }

        if ($eventType === 'installed_apps') {
            $studentAppPolicyService->syncInstalledApps($device, $request->input('payload.apps', []));
        }

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
