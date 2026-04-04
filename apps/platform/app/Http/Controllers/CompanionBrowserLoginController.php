<?php

namespace App\Http\Controllers;

use App\Models\StudentDevice;
use App\Services\CompanionBrowserLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanionBrowserLoginController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        CompanionBrowserLoginService $companionBrowserLoginService,
    ): RedirectResponse {
        $payload = $companionBrowserLoginService->consume($token);
        abort_unless($payload !== null, 404);

        $device = StudentDevice::query()
            ->with('student.user')
            ->findOrFail((int) $payload['student_device_id']);

        $user = $device->student?->user;
        abort_unless($user !== null && $user->is_active, 404);

        Auth::login($user, true);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->to((string) ($payload['redirect_path'] ?? route('student.home', absolute: false)));
    }
}
