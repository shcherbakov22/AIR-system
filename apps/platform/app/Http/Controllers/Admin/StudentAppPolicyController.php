<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManageStudentDeviceRequest;
use App\Models\Student;
use App\Models\StudentAppPolicy;
use App\Services\StudentAppPolicyService;
use Illuminate\Http\RedirectResponse;

class StudentAppPolicyController extends Controller
{
    public function permit(
        ManageStudentDeviceRequest $request,
        Student $student,
        StudentAppPolicy $studentAppPolicy,
        StudentAppPolicyService $studentAppPolicyService,
    ): RedirectResponse {
        abort_unless($studentAppPolicy->student_id === $student->id, 404);

        $studentAppPolicyService->permit($studentAppPolicy, $request->user()->id);

        return back()->with('success', "{$studentAppPolicy->app_name} permitted.");
    }

    public function block(
        ManageStudentDeviceRequest $request,
        Student $student,
        StudentAppPolicy $studentAppPolicy,
        StudentAppPolicyService $studentAppPolicyService,
    ): RedirectResponse {
        abort_unless($studentAppPolicy->student_id === $student->id, 404);

        $studentAppPolicyService->block($studentAppPolicy, $request->user()->id);

        return back()->with('success', "{$studentAppPolicy->app_name} blocked.");
    }
}
