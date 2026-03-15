<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompanionDeviceRequest;
use App\Http\Requests\Api\CompanionEnrollmentRequest;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CompanionEnrollmentController extends Controller
{
    public function store(CompanionEnrollmentRequest $request): JsonResponse
    {
        $user = User::query()
            ->with('student')
            ->where('username', $request->string('username')->toString())
            ->firstOrFail();

        $device = StudentDevice::query()->updateOrCreate(
            [
                'student_id' => $user->student->id,
                'device_key' => $request->string('device_key')->toString(),
            ],
            [
                'label' => $request->string('label')->toString(),
                'hostname' => $request->input('hostname'),
                'platform' => $request->string('platform')->toString(),
                'app_version' => $request->input('app_version'),
                'last_seen_at' => now(),
                'last_seen_ip' => $request->ip(),
                'revoked_at' => null,
                'revoked_by_user_id' => null,
                'meta' => $request->input('meta', []),
            ],
        );

        $token = $device->issueToken();

        return response()->json([
            'accepted' => true,
            'token' => $token,
            'device' => [
                'id' => $device->id,
                'device_key' => $device->device_key,
                'label' => $device->label,
                'platform' => $device->platform,
                'hostname' => $device->hostname,
            ],
            'student' => [
                'id' => $user->student->id,
                'display_name' => $user->student->display_name,
                'username' => $user->username,
            ],
        ]);
    }

    public function renew(CompanionDeviceRequest $request): JsonResponse
    {
        $device = $request->device();
        $token = $device->issueToken();

        $device->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'accepted' => true,
            'token' => $token,
            'device' => [
                'id' => $device->id,
                'device_key' => $device->device_key,
                'label' => $device->label,
            ],
        ]);
    }

    public function revoke(CompanionDeviceRequest $request): JsonResponse
    {
        $device = $request->device();

        $device->forceFill([
            'revoked_at' => now(),
            'revoked_by_user_id' => null,
        ])->save();

        return response()->json([
            'accepted' => true,
            'revoked' => true,
        ]);
    }
}
