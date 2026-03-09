<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\PauseScheduleRunRequest;
use App\Http\Requests\Student\ResumeScheduleRunRequest;
use App\Http\Requests\Student\StartScheduleRunRequest;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ScheduleRunController extends Controller
{
    public function store(StartScheduleRunRequest $request, ScheduleTemplate $scheduleTemplate): RedirectResponse
    {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $scheduleTemplate) {
            Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();

            $ownedScheduleTemplate = ScheduleTemplate::query()
                ->with(['entries.taskTemplate'])
                ->whereKey($scheduleTemplate->id)
                ->where('student_id', $studentId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ownedScheduleTemplate->entries->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'В этом расписании пока нет блоков для выполнения.',
                ];
            }

            $hasActiveRun = ScheduleRun::query()
                ->where('student_id', $studentId)
                ->whereIn('status', ['active', 'paused'])
                ->exists();

            if ($hasActiveRun) {
                return [
                    'success' => false,
                    'message' => 'Завершите текущее расписание, прежде чем запускать другое.',
                ];
            }

            $hasActiveTaskSession = TaskSession::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveTaskSession) {
                return [
                    'success' => false,
                    'message' => 'Остановите текущую сессию задания перед запуском расписания.',
                ];
            }

            $scheduleRun = ScheduleRun::create([
                'student_id' => $studentId,
                'schedule_template_id' => $ownedScheduleTemplate->id,
                'status' => 'active',
                'schedule_name_snapshot' => $ownedScheduleTemplate->name,
                'schedule_weekday_snapshot' => $ownedScheduleTemplate->weekday->label(),
                'schedule_notes_snapshot' => $ownedScheduleTemplate->notes,
                'started_at' => now(),
                'started_by_user_id' => $request->user()->id,
            ]);

            foreach ($ownedScheduleTemplate->entries as $entry) {
                $scheduleRun->blocks()->create([
                    'schedule_entry_id' => $entry->id,
                    'task_template_id' => $entry->task_template_id,
                    'position' => $entry->position,
                    'status' => 'pending',
                    'start_time_snapshot' => substr((string) $entry->start_time, 0, 5),
                    'duration_minutes_snapshot' => $entry->duration_minutes,
                    'task_title_snapshot' => $entry->resolvedTaskTitle(),
                    'task_summary_snapshot' => $entry->resolvedTaskSummary(),
                    'task_instructions_snapshot' => $entry->resolvedTaskInstructions(),
                    'entry_notes_snapshot' => $entry->notes,
                ]);
            }

            return [
                'success' => true,
                'message' => "Расписание {$ownedScheduleTemplate->name} запущено.",
            ];
        });

        return redirect()
            ->route('student.home')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function pause(PauseScheduleRunRequest $request, ScheduleRun $scheduleRun): RedirectResponse
    {
        $studentId = $request->user()?->student?->id;
        $student = $request->user()?->student?->loadMissing('setting');

        if (! $studentId || ! $student) {
            abort(403);
        }

        if (! $student->canUseAdHocTimer()) {
            return redirect()
                ->route('student.home')
                ->with('error', 'Собственные таймеры для этого ученика отключены.');
        }

        $result = DB::transaction(function () use ($request, $studentId, $scheduleRun) {
            Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();

            $ownedScheduleRun = ScheduleRun::query()
                ->whereKey($scheduleRun->id)
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $activeTaskSession = TaskSession::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($activeTaskSession && $activeTaskSession->schedule_run_id !== $ownedScheduleRun->id) {
                return [
                    'success' => false,
                    'message' => 'Остановите текущий таймер перед постановкой расписания на паузу.',
                ];
            }

            if ($activeTaskSession && $activeTaskSession->schedule_run_block_id) {
                /** @var ScheduleRunBlock $scheduleRunBlock */
                $scheduleRunBlock = ScheduleRunBlock::query()
                    ->whereKey($activeTaskSession->schedule_run_block_id)
                    ->where('schedule_run_id', $ownedScheduleRun->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $endedAt = now();
                $durationSeconds = (int) max(0, $activeTaskSession->started_at?->diffInSeconds($endedAt) ?? 0);

                $activeTaskSession->update([
                    'status' => 'paused',
                    'ended_at' => $endedAt,
                    'duration_seconds' => $durationSeconds,
                    'completion_notes' => 'Пауза для собственного таймера.',
                    'stopped_by_user_id' => $request->user()->id,
                ]);

                $scheduleRunBlock->update([
                    'status' => 'paused',
                ]);
            }

            $ownedScheduleRun->update([
                'status' => 'paused',
            ]);

            TaskSession::create([
                'student_id' => $studentId,
                'task_assignment_id' => null,
                'schedule_run_id' => null,
                'schedule_run_block_id' => null,
                'task_template_id' => null,
                'status' => 'active',
                'task_title_snapshot' => $request->string('task_title')->toString(),
                'task_summary_snapshot' => $request->input('notes'),
                'task_instructions_snapshot' => null,
                'assignment_notes_snapshot' => null,
                'planned_duration_minutes' => (int) $request->input('duration_minutes'),
                'started_at' => now(),
                'started_by_user_id' => $request->user()->id,
            ]);

            return [
                'success' => true,
                'message' => "Расписание {$ownedScheduleRun->schedule_name_snapshot} поставлено на паузу. Собственный таймер запущен.",
            ];
        });

        return redirect()
            ->route('student.home')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function resume(ResumeScheduleRunRequest $request, ScheduleRun $scheduleRun): RedirectResponse
    {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $scheduleRun) {
            Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();

            $ownedScheduleRun = ScheduleRun::query()
                ->with('blocks')
                ->whereKey($scheduleRun->id)
                ->where('student_id', $studentId)
                ->where('status', 'paused')
                ->lockForUpdate()
                ->firstOrFail();

            $hasActiveTaskSession = TaskSession::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveTaskSession) {
                return [
                    'success' => false,
                    'message' => 'Остановите текущий таймер перед возобновлением расписания.',
                ];
            }

            /** @var ScheduleRunBlock|null $pausedBlock */
            $pausedBlock = ScheduleRunBlock::query()
                ->where('schedule_run_id', $ownedScheduleRun->id)
                ->where('status', 'paused')
                ->orderBy('position')
                ->lockForUpdate()
                ->first();

            $ownedScheduleRun->update([
                'status' => 'active',
            ]);

            if (! $pausedBlock) {
                return [
                    'success' => true,
                    'message' => "Расписание {$ownedScheduleRun->schedule_name_snapshot} возобновлено.",
                ];
            }

            $startedAt = now();

            $pausedBlock->update([
                'status' => 'in_progress',
            ]);

            TaskSession::create([
                'student_id' => $studentId,
                'task_assignment_id' => null,
                'schedule_run_id' => $ownedScheduleRun->id,
                'schedule_run_block_id' => $pausedBlock->id,
                'task_template_id' => $pausedBlock->task_template_id,
                'status' => 'active',
                'task_title_snapshot' => $pausedBlock->task_title_snapshot,
                'task_summary_snapshot' => $pausedBlock->task_summary_snapshot,
                'task_instructions_snapshot' => $pausedBlock->task_instructions_snapshot,
                'assignment_notes_snapshot' => $pausedBlock->entry_notes_snapshot,
                'planned_duration_minutes' => $pausedBlock->duration_minutes_snapshot,
                'started_at' => $startedAt,
                'started_by_user_id' => $request->user()->id,
            ]);

            return [
                'success' => true,
                'message' => "Сессия задания {$pausedBlock->task_title_snapshot} возобновлена.",
            ];
        });

        return redirect()
            ->route('student.home')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
