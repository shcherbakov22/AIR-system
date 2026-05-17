<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HiddenScheduleBlockTimeController extends Controller
{
    private function findStudentByUsername(string $username): ?Student
    {
        return Student::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('username', $username))
            ->first();
    }

    private function resolveCurrentScheduleRunForStudent(Student $student): ?ScheduleRun
    {
        return ScheduleRun::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['active', 'paused'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->with(['blocks' => fn ($query) => $query
                ->orderBy('position')
                ->with('taskSessions')])
            ->first()
            ?? ScheduleRun::query()
                ->where('student_id', $student->id)
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->with(['blocks' => fn ($query) => $query
                    ->orderBy('position')
                    ->with('taskSessions')])
                ->first();
    }

    public function show(Request $request): Response
    {
        $studentUsername = trim((string) $request->query('student', ''));

        $student = null;
        $selectedScheduleRun = null;
        $blocks = [];

        if ($studentUsername !== '') {
            $student = $this->findStudentByUsername($studentUsername);

            if ($student) {
                $selectedScheduleRun = $this->resolveCurrentScheduleRunForStudent($student);

                if ($selectedScheduleRun) {
                    $blocks = $selectedScheduleRun->blocks
                        ->map(fn (ScheduleRunBlock $block) => [
                            'id' => $block->id,
                            'position' => $block->position,
                            'status' => $block->status,
                            'start_time' => $block->start_time_snapshot,
                            'task_title' => $block->task_title_snapshot,
                            'shown_duration_minutes' => $block->duration_minutes_snapshot,
                            'shown_duration_seconds' => (int) $block->taskSessions->max(
                                fn ($taskSession) => (int) ($taskSession->duration_seconds ?? 0)
                            ),
                        ])
                        ->values()
                        ->all();
                }
            }
        }

        return Inertia::render('Admin/Hidden/ScheduleBlockTime', [
            'student_query' => $studentUsername,
            'student' => $student
                ? [
                    'id' => $student->id,
                    'display_name' => $student->display_name,
                    'username' => $student->user?->username,
                ]
                : null,
            'schedule_run' => $selectedScheduleRun
                ? [
                    'id' => $selectedScheduleRun->id,
                    'status' => $selectedScheduleRun->status,
                    'started_at_label' => $selectedScheduleRun->started_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                ]
                : null,
            'blocks' => $blocks,
            'status_message' => session('status'),
            'error_message' => session('error'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_username' => ['required', 'string', 'max:100'],
            'schedule_run_block_position' => ['required', 'integer', 'min:1', 'max:500'],
            'shown_duration_minutes' => ['required', 'integer', 'min:0', 'max:600'],
            'shown_duration_seconds' => ['required', 'integer', 'min:0', 'max:59'],
        ]);

        $student = $this->findStudentByUsername($validated['student_username']);

        if (! $student) {
            return redirect()
                ->route('admin.hidden.schedule-block-time.show', ['student' => $validated['student_username']])
                ->with('error', 'Student not found.');
        }

        $selectedScheduleRun = $this->resolveCurrentScheduleRunForStudent($student);

        if (! $selectedScheduleRun) {
            return redirect()
                ->route('admin.hidden.schedule-block-time.show', ['student' => $validated['student_username']])
                ->with('error', 'No schedule run found for this student.');
        }

        $block = ScheduleRunBlock::query()
            ->where('schedule_run_id', $selectedScheduleRun->id)
            ->where('position', (int) $validated['schedule_run_block_position'])
            ->first();

        if (! $block) {
            return redirect()
                ->route('admin.hidden.schedule-block-time.show', ['student' => $validated['student_username']])
                ->with('error', 'Block not found for this student.');
        }

        $newDurationMinutes = (int) $validated['shown_duration_minutes'];
        $newDurationSeconds = (int) $validated['shown_duration_seconds'];
        $totalDurationSeconds = max(0, ($newDurationMinutes * 60) + $newDurationSeconds);

        $block->update([
            'duration_minutes_snapshot' => $newDurationMinutes,
        ]);

        $block->taskSessions()->update([
            'planned_duration_minutes' => $newDurationMinutes,
            'duration_seconds' => 0,
        ]);

        $latestTaskSession = $block->taskSessions()
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if ($latestTaskSession) {
            $latestTaskSession->update([
                'planned_duration_minutes' => $newDurationMinutes,
                'duration_seconds' => $totalDurationSeconds,
                'started_at' => $latestTaskSession->status === 'active'
                    ? now()
                    : $latestTaskSession->started_at,
            ]);
        }

        return redirect()
            ->route('admin.hidden.schedule-block-time.show', ['student' => $validated['student_username']])
            ->with('status', sprintf(
                'Updated block position %d to %d:%02d.',
                $block->position,
                $newDurationMinutes,
                $newDurationSeconds
            ));
    }
}
