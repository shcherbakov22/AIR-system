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
use App\Services\StudentPushUpCounterService;
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
            'settings' => [
                'can_manage_own_schedule' => $student->canManageOwnSchedule(),
                'can_use_ad_hoc_timer' => $student->canUseAdHocTimer(),
                'look_away_event_threshold' => $student->setting?->look_away_event_threshold ?? 3,
                'preferred_timezone' => $student->setting?->preferred_timezone,
            ],
            'consequence_profile' => [
                'default_push_up_count' => $student->consequenceProfile?->default_push_up_count ?? 0,
                'current_push_up_count' => $student->consequenceProfile?->current_push_up_count ?? 10,
                'rest_duration_seconds' => $student->consequenceProfile?->rest_duration_seconds ?? 0,
                'legacy_owner_user_id' => $student->consequenceProfile?->legacy_owner_user_id,
            ],
            'user' => [
                'id' => $student->user->id,
                'username' => $student->user->username,
                'name' => $student->user->name,
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
                'email' => null,
                'role' => UserRole::Student,
                'is_active' => true,
                'password' => Hash::make($request->string('password')->toString()),
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'display_name' => $request->string('display_name')->toString(),
                'status' => 'active',
                'notes' => null,
            ]);

            StudentSetting::create([
                'student_id' => $student->id,
                'can_manage_own_schedule' => $request->boolean('can_manage_own_schedule'),
                'can_use_ad_hoc_timer' => $request->boolean('can_use_ad_hoc_timer'),
                'look_away_event_threshold' => (int) $request->input('look_away_event_threshold'),
                'look_away_event_count' => 0,
                'look_away_task_session_id' => null,
                'preferred_timezone' => $request->input('preferred_timezone') ?: null,
            ]);

            StudentConsequenceProfile::create([
                'student_id' => $student->id,
                'default_push_up_count' => (int) $request->input('default_push_up_count'),
                'current_push_up_count' => 10,
                'rest_duration_seconds' => (int) $request->input('rest_duration_seconds'),
                'legacy_owner_user_id' => null,
                'notes' => null,
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
                'email' => null,
            ]);

            $student->update([
                'display_name' => $request->string('display_name')->toString(),
            ]);

            $student->setting()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'can_manage_own_schedule' => $request->boolean('can_manage_own_schedule'),
                    'can_use_ad_hoc_timer' => $request->boolean('can_use_ad_hoc_timer'),
                    'look_away_event_threshold' => (int) $request->input('look_away_event_threshold'),
                    'preferred_timezone' => $request->input('preferred_timezone') ?: null,
                ],
            );

            $student->consequenceProfile()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'default_push_up_count' => (int) $request->input('default_push_up_count'),
                    'current_push_up_count' => $student->consequenceProfile?->current_push_up_count ?? 10,
                    'rest_duration_seconds' => (int) $request->input('rest_duration_seconds'),
                    'notes' => $student->consequenceProfile?->notes,
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

    public function updatePushUpCounter(\Illuminate\Http\Request $request, Student $student, StudentPushUpCounterService $pushUpCounterService): RedirectResponse
    {
        $action = $request->validate([
            'action' => ['required', 'in:increment,decrement,reset'],
        ])['action'];

        DB::transaction(function () use ($action, $student, $pushUpCounterService) {
            if ($action === 'increment') {
                $pushUpCounterService->increment($student);
                return;
            }

            if ($action === 'decrement') {
                $pushUpCounterService->decrement($student);
                return;
            }

            $pushUpCounterService->reset($student);
        });

        return redirect()->back()->with('success', 'Push-up counter updated.');
    }
}
