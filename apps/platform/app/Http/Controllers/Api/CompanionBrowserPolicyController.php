<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompanionDeviceRequest;
use App\Http\Requests\Api\StoreCompanionBrowserAccessRequest;
use App\Http\Requests\Api\StoreCompanionBrowserVisitRequest;
use App\Services\BrowserAccountabilityPolicyService;
use Illuminate\Http\JsonResponse;

class CompanionBrowserPolicyController extends Controller
{
    public function show(
        CompanionDeviceRequest $request,
        BrowserAccountabilityPolicyService $browserPolicyService,
    ): JsonResponse {
        $device = $request->device();

        $device->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'accepted' => true,
            'policy' => $browserPolicyService->policyForDevice($device),
        ]);
    }

    public function visit(
        StoreCompanionBrowserVisitRequest $request,
        BrowserAccountabilityPolicyService $browserPolicyService,
    ): JsonResponse {
        $device = $request->device();
        $visit = $browserPolicyService->logVisit(
            $device,
            $request->string('url')->toString(),
            $request->input('page_title'),
            $request->input('meta', []),
        );

        $device->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'accepted' => true,
            'visit' => [
                'id' => $visit->id,
                'decision' => $visit->decision,
                'mode' => $visit->mode,
                'host' => $visit->host,
                'registrable_domain' => $visit->registrable_domain,
                'matched_rule_id' => $visit->matched_rule_id,
            ],
        ]);
    }

    public function requestAccess(
        StoreCompanionBrowserAccessRequest $request,
        BrowserAccountabilityPolicyService $browserPolicyService,
    ): JsonResponse {
        $accessRequest = $browserPolicyService->createAccessRequest(
            $request->device(),
            $request->string('url')->toString(),
            $request->input('reason'),
        );

        return response()->json([
            'accepted' => true,
            'request' => [
                'id' => $accessRequest->id,
                'status' => $accessRequest->status,
                'host' => $accessRequest->host,
                'registrable_domain' => $accessRequest->registrable_domain,
                'task_template_id' => $accessRequest->task_template_id,
            ],
        ], $accessRequest->status === 'pending' ? 201 : 202);
    }
}
