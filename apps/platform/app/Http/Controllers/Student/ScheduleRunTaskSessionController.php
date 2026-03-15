<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StartScheduleRunBlockTaskSessionRequest;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\Violation;
use App\Services\AutomaticObserveTheTimeViolationService;
use App\Services\StudentCommunicationGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ScheduleRunTaskSessionController extends Controller
{
    public function store(
        StartScheduleRunBlockTaskSessionRequest $request,
        ScheduleRun $scheduleRun,
        ScheduleRunBlock $scheduleRunBlock,
        AutomaticObserveTheTimeViolationService $automaticViolationService,
        StudentCommunicationGateService $communicationGateService,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $scheduleRun, $scheduleRunBlock, $automaticViolationService, $communicationGateService) {
            $student = Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();
            $automaticViolationService->evaluate($student);

            if ($blockingMessage = $this->blockingViolationMessage($student)) {
                return [
                    'success' => false,
                    'message' => $blockingMessage,
                ];
            }

            if ($blockingMessage = $communicationGateService->blockingMessage($student)) {
                return [
                    'success' => false,
                    'message' => $blockingMessage,
                ];
            }

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
                    'message' => 'Stop the current task session before starting a new one.',
                ];
            }

            $hasRunningScheduleBlock = $ownedScheduleRun->blocks()
                ->where('status', 'in_progress')
                ->exists();

            if ($hasRunningScheduleBlock) {
                return [
                    'success' => false,
                    'message' => 'Finish the current schedule block before starting another one.',
                ];
            }

            if ($ownedScheduleRunBlock->status !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'This schedule block is not available to start right now.',
                ];
            }

            $automaticViolationService->clearDismissedViolationsForNewTask($student);

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
                'duration_seconds' => 0,
                'started_at' => $startedAt,
                'started_by_user_id' => $request->user()->id,
            ]);

            return [
                'success' => true,
                'message' => "Task session {$ownedScheduleRunBlock->task_title_snapshot} started.",
            ];
        });

        return redirect()
            ->route('student.home')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    protected function blockingViolationMessage(Student $student): ?string
    {
        /** @var Violation|null $violation */
        $violation = $student->violations()
            ->where('status', 'open')
            ->orderByDesc('occurred_at')
            ->first();

        if (! $violation) {
            return null;
        }

        $occurredAt = $violation->occurred_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i');
        $timeSuffix = $occurredAt ? " ({$occurredAt})" : '';

        return "There is an open violation: {$violation->rule_title_snapshot}{$timeSuffix}. Ask your mentor to close it before continuing the schedule.";
    }
}
