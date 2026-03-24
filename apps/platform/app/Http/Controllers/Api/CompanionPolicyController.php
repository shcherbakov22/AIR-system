<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompanionDeviceRequest;
use App\Services\DevicePolicyService;
use Illuminate\Http\JsonResponse;

class CompanionPolicyController extends Controller
{
    public function show(
        CompanionDeviceRequest $request,
        DevicePolicyService $devicePolicyService,
    ): JsonResponse {
        $device = $request->device();
        $policy = $devicePolicyService->buildForDevice($device);
        $policyHash = $devicePolicyService->policyHash($policy);
        $networkState = [
            'status' => 'removed',
            'reason' => 'internet_control_removed',
            'policy' => $policy['internet_policy'],
        ];

        $device->forceFill([
            'last_policy_hash' => $policyHash,
            'last_network_state' => $networkState,
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'accepted' => true,
            'policy_hash' => $policyHash,
            'policy' => $policy,
            'network_state' => $networkState,
        ]);
    }
}
