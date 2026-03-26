<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Violation;
use App\Services\PushUpSessionService;
use Illuminate\Http\RedirectResponse;

class PushUpSessionController extends Controller
{
    public function store(Violation $violation, PushUpSessionService $service): RedirectResponse
    {
        $service->createOrReuse($violation, request()->user());

        return redirect()->back()->with('success', 'Push-up session queued.');
    }
}
