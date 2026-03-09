<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StartScheduleRunBlockTaskSessionRequest;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\TaskSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ScheduleRunTaskSessionController extends Controller
{
    public function store(
        StartScheduleRunBlockTaskSessionRequest $request,
        ScheduleRun $scheduleRun,
        ScheduleRunBlock $scheduleRunBlock,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $scheduleRun, $scheduleRunBlock) {
            Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();

            $ownedScheduleRun = ScheduleRun::query()
                ->whereKey($scheduleRun->id)
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $ownedScheduleRunBlock = ScheduleRunBlock::query()
                ->whereKey($scheduleRunBlock->id)
                ->where('schedule_run_id', $ownedScheduleRun->id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasActiveTaskSession = TaskSession::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->exists();

            if ($hasActiveTaskSession) {
                return [
                    'success' => false,
                    'message' => 'Остановите текущую сессию задания, прежде чем запускать новую.',
                ];
            }

            $hasRunningScheduleBlock = $ownedScheduleRun->blocks()
                ->where('status', 'in_progress')
                ->exists();

            if ($hasRunningScheduleBlock) {
                return [
                    'success' => false,
                    'message' => 'Завершите текущий блок расписания, прежде чем запускать другой.',
                ];
            }

            if ($ownedScheduleRunBlock->status !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'Этот блок расписания сейчас недоступен для запуска.',
                ];
            }

            $hasIncompleteEarlierBlock = $ownedScheduleRun->blocks()
                ->where('position', '<', $ownedScheduleRunBlock->position)
                ->where('status', '!=', 'completed')
                ->exists();

            if ($hasIncompleteEarlierBlock) {
                return [
                    'success' => false,
                    'message' => 'Запускайте задания расписания по порядку.',
                ];
            }

            $startedAt = now();

            $ownedScheduleRunBlock->update([
                'status' => 'in_progress',
                'started_at' => $startedAt,
            ]);

            TaskSession::create([
                'student_id' => $studentId,
                'task_assignment_id' => null,
                'schedule_run_id' => $ownedScheduleRun->id,
                'schedule_run_block_id' => $ownedScheduleRunBlock->id,
                'task_template_id' => $ownedScheduleRunBlock->task_template_id,
                'status' => 'active',
                'task_title_snapshot' => $ownedScheduleRunBlock->task_title_snapshot,
                'task_summary_snapshot' => $ownedScheduleRunBlock->task_summary_snapshot,
                'task_instructions_snapshot' => $ownedScheduleRunBlock->task_instructions_snapshot,
                'assignment_notes_snapshot' => $ownedScheduleRunBlock->entry_notes_snapshot,
                'planned_duration_minutes' => $ownedScheduleRunBlock->duration_minutes_snapshot,
                'started_at' => $startedAt,
                'started_by_user_id' => $request->user()->id,
            ]);

            return [
                'success' => true,
                'message' => "Сессия задания {$ownedScheduleRunBlock->task_title_snapshot} началась.",
            ];
        });

        return redirect()
            ->route('student.home')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
