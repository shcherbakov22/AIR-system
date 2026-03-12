<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentPasswordRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\Student;
use App\Models\StudentConsequenceProfile;
use App\Models\StudentSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    protected function toPayload(Student $student): array
    {
        $student->loadMissing(['user', 'setting', 'consequenceProfile']);

        return [
            'id' => $student->id,
            'display_name' => $student->display_name,
            'status' => $student->status,
            'notes' => $student->notes,
            'settings' => [
                'can_manage_own_schedule' => $student->canManageOwnSchedule(),
                'can_use_ad_hoc_timer' => $student->canUseAdHocTimer(),
                'preferred_timezone' => $student->setting?->preferred_timezone,
            ],
            'consequence_profile' => [
                'default_push_up_count' => $student->consequenceProfile?->default_push_up_count ?? 0,
                'rest_duration_seconds' => $student->consequenceProfile?->rest_duration_seconds ?? 0,
                'legacy_owner_user_id' => $student->consequenceProfile?->legacy_owner_user_id,
                'notes' => $student->consequenceProfile?->notes,
            ],
            'user' => [
                'id' => $student->user->id,
                'username' => $student->user->username,
                'name' => $student->user->name,
                'email' => $student->user->email,
                'is_active' => $student->user->is_active,
                'last_login_at' => $student->user->last_login_at?->toAtomString(),
            ],
        ];
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Students/Create');
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $student = DB::transaction(function () use ($request) {
            $user = User::create([
                'username' => $request->string('username')->toString(),
                'name' => $request->string('name')->toString(),
                'email' => $request->input('email'),
                'role' => UserRole::Student,
                'is_active' => $request->boolean('is_active'),
                'password' => Hash::make($request->string('password')->toString()),
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'display_name' => $request->string('display_name')->toString(),
                'status' => $request->string('status')->toString(),
                'notes' => $request->input('notes') ?: null,
            ]);

            StudentSetting::create([
                'student_id' => $student->id,
                'can_manage_own_schedule' => $request->boolean('can_manage_own_schedule'),
                'can_use_ad_hoc_timer' => $request->boolean('can_use_ad_hoc_timer'),
                'preferred_timezone' => $request->input('preferred_timezone') ?: null,
            ]);

            StudentConsequenceProfile::create([
                'student_id' => $student->id,
                'default_push_up_count' => (int) $request->input('default_push_up_count'),
                'rest_duration_seconds' => (int) $request->input('rest_duration_seconds'),
                'legacy_owner_user_id' => null,
                'notes' => $request->input('consequence_notes') ?: null,
            ]);

            return $student;
        });

        return redirect()
            ->route('admin.students.index')
            ->with('success', "Student {$student->display_name} has been created.");
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('Admin/Students/Edit', [
            'student' => $this->toPayload($student),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student) {
            $student->loadMissing(['user', 'setting', 'consequenceProfile']);

            $student->user->update([
                'username' => $request->string('username')->toString(),
                'name' => $request->string('name')->toString(),
                'email' => $request->input('email'),
                'is_active' => $request->boolean('is_active'),
            ]);

            $student->update([
                'display_name' => $request->string('display_name')->toString(),
                'status' => $request->string('status')->toString(),
                'notes' => $request->input('notes') ?: null,
            ]);

            $student->setting()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'can_manage_own_schedule' => $request->boolean('can_manage_own_schedule'),
                    'can_use_ad_hoc_timer' => $request->boolean('can_use_ad_hoc_timer'),
                    'preferred_timezone' => $request->input('preferred_timezone') ?: null,
                ],
            );

            $student->consequenceProfile()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'default_push_up_count' => (int) $request->input('default_push_up_count'),
                    'rest_duration_seconds' => (int) $request->input('rest_duration_seconds'),
                    'notes' => $request->input('consequence_notes') ?: null,
                ],
            );
        });

        return redirect()
            ->route('admin.students.index')
            ->with('success', "Student {$student->fresh()->display_name} has been updated.");
    }

    public function updatePassword(UpdateStudentPasswordRequest $request, Student $student): RedirectResponse
    {
        $student->loadMissing('user');

        $student->user->update([
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        return redirect()
            ->route('admin.students.edit', $student)
            ->with('success', "Password for {$student->display_name} has been updated.");
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->loadMissing('user');

        $studentName = $student->display_name;

        DB::transaction(function () use ($student) {
            $student->user->delete();
        });

        return redirect()
            ->route('admin.students.index')
            ->with('success', "Student {$studentName} has been deleted.");
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Students/Index', [
            'students' => Student::query()
                ->with('user')
                ->orderBy('display_name')
                ->get()
                ->map(fn (Student $student) => $this->toPayload($student)),
        ]);
    }
}
