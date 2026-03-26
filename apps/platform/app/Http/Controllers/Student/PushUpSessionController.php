<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Violation;
use App\Services\PushUpSessionService;
use Illuminate\Http\RedirectResponse;

class PushUpSessionController extends Controller
{
    public function store(Violation $violation, PushUpSessionService $service): RedirectResponse
    {
        $student = request()->user()?->student;
        abort_unless($student !== null && $violation->student_id === $student->id, 404);

        $service->createOrReuse($violation, request()->user());

        return redirect()->back()->with('success', 'Push-up session queued.');
    }
}
