<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCompanionBrowserSessionRequest;
use App\Services\CompanionBrowserLoginService;
use Illuminate\Http\JsonResponse;

class CompanionBrowserSessionController extends Controller
{
    public function store(
        StoreCompanionBrowserSessionRequest $request,
        CompanionBrowserLoginService $companionBrowserLoginService,
    ): JsonResponse {
        $device = $request->device();

        return response()->json([
            'accepted' => true,
            'browser_login_url' => $companionBrowserLoginService->issueUrl(
                $device,
                $request->input('redirect_path'),
            ),
        ]);
    }
}
