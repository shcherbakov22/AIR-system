<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushUpStation;
use App\Models\PushUpStationCommand;
use App\Services\PushUpSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PushUpStationController extends Controller
{
    public function restart(Request $request, PushUpStation $pushUpStation): RedirectResponse
    {
        $pushUpStation->commands()->create([
            'station_key' => $pushUpStation->station_key,
            'command' => 'restart_esp',
            'payload' => ['reason' => 'admin_requested'],
            'status' => PushUpStationCommand::STATUS_PENDING,
            'requested_by_user_id' => $request->user()?->id,
        ]);

        return back()->with('status', 'Push-up counter restart queued.');
    }

    public function endSession(Request $request, PushUpStation $pushUpStation, PushUpSessionService $service): RedirectResponse
    {
        $session = $pushUpStation->sessions()
            ->whereIn('status', [
                PushUpSessionService::STATUS_CLAIMED,
                PushUpSessionService::STATUS_RUNNING,
            ])
            ->latest('id')
            ->first();

        if ($session) {
            $service->fail($session, 'Ended remotely by admin.');
        }

        $pushUpStation->commands()->create([
            'station_key' => $pushUpStation->station_key,
            'command' => 'end_session',
            'payload' => ['session_id' => $session?->id, 'reason' => 'admin_requested'],
            'status' => PushUpStationCommand::STATUS_PENDING,
            'requested_by_user_id' => $request->user()?->id,
        ]);

        return back()->with('status', 'Push-up counter session end queued.');
    }
}
