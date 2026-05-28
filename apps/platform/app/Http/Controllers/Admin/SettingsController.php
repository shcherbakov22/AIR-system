<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentPushUpCounterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const DEFAULT_CAPTURE_INTERVAL_SECONDS = 30;

    public function index(): Response
    {
        $students = Student::query()
            ->with(['user', 'setting', 'consequenceProfile'])
            ->orderBy('display_name')
            ->get();

        return Inertia::render('Admin/Settings/Index', [
            'students' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
                'settings' => [
                    'screen_capture_interval_seconds' => $student->setting?->screen_capture_interval_seconds ?? self::DEFAULT_CAPTURE_INTERVAL_SECONDS,
                    'camera_capture_interval_seconds' => $student->setting?->camera_capture_interval_seconds ?? self::DEFAULT_CAPTURE_INTERVAL_SECONDS,
                ],
                'consequence_profile' => [
                    'current_push_up_count' => $student->consequenceProfile?->current_push_up_count ?? StudentPushUpCounterService::DEFAULT_COUNT,
                    'increment_push_up_count_per_violation' => $student->consequenceProfile?->increment_push_up_count_per_violation ?? true,
                ],
            ])->all(),
        ]);
    }

    public function updateStudent(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'increment_push_up_count_per_violation' => ['required', 'boolean'],
            'screen_capture_interval_seconds' => ['required', 'integer', 'min:15', 'max:3600'],
            'camera_capture_interval_seconds' => ['required', 'integer', 'min:15', 'max:3600'],
        ]);

        DB::transaction(function () use ($student, $validated) {
            $student->loadMissing(['setting', 'consequenceProfile']);

            $student->setting()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'can_manage_own_schedule' => $student->setting?->can_manage_own_schedule ?? true,
                    'can_use_ad_hoc_timer' => $student->setting?->can_use_ad_hoc_timer ?? true,
                    'look_away_event_threshold' => $student->setting?->look_away_event_threshold ?? 3,
                    'look_away_event_count' => $student->setting?->look_away_event_count ?? 0,
                    'look_away_task_session_id' => $student->setting?->look_away_task_session_id,
                    'preferred_timezone' => $student->setting?->preferred_timezone,
                    'screen_capture_interval_seconds' => (int) $validated['screen_capture_interval_seconds'],
                    'camera_capture_interval_seconds' => (int) $validated['camera_capture_interval_seconds'],
                ],
            );

            $student->consequenceProfile()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'default_push_up_count' => $student->consequenceProfile?->default_push_up_count ?? 0,
                    'current_push_up_count' => $student->consequenceProfile?->current_push_up_count ?? StudentPushUpCounterService::DEFAULT_COUNT,
                    'increment_push_up_count_per_violation' => (bool) $validated['increment_push_up_count_per_violation'],
                    'rest_duration_seconds' => $student->consequenceProfile?->rest_duration_seconds ?? 0,
                    'legacy_owner_user_id' => $student->consequenceProfile?->legacy_owner_user_id,
                    'notes' => $student->consequenceProfile?->notes,
                ],
            );
        });

        return redirect()->back()->with('success', "{$student->display_name} settings updated.");
    }
}
