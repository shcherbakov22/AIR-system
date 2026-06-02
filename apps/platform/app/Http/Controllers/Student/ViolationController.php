<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Violation;
use App\Services\ViolationFalsePositiveService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class ViolationController extends Controller
{
    public function falsePositive(Violation $violation, ViolationFalsePositiveService $service): RedirectResponse
    {
        $student = request()->user()?->student;

        abort_unless($student && $violation->student_id === $student->id, 403);

        try {
            $service->markFalsePositive($violation, request()->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Violation marked as a false positive for mentor review.');
    }
}
