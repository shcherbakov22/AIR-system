<?php

namespace App\Http\Controllers\Student;

use App\Enums\ScheduleWeekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\PauseScheduleRunRequest;
use App\Http\Requests\Student\ResumeScheduleRunRequest;
use App\Http\Requests\Student\StartScheduleRunRequest;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\Violation;
use App\Services\AutomaticObserveTheTimeViolationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ScheduleRunController extends Controller
{
    protected function scheduleWeekdayLabel(mixed $weekday): string
    {
        if ($weekday instanceof ScheduleWeekday) {
            return $weekday->label();
        }

        if (is_string($weekday)) {
            return ScheduleWeekday::tryFrom($weekday)?->label() ?? $weekday;
        }

        return 'Без дня';
    }

    public function store(
        StartScheduleRunRequest $request,
        ScheduleTemplate $scheduleTemplate,
        AutomaticObserveTheTimeViolationService $automaticViolationService,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $scheduleTemplate, $automaticViolationService) {
            $student = Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();
            $automaticViolationService->evaluate($student);

            if ($blockingMessage = $this->blockingViolationMessage($student)) {
                return [
                    'success' => false,
                    'message' => $blockingMessage,
                ];
            }

            $ownedScheduleTemplate = ScheduleTemplate::query()
                ->with(['entries.taskTemplate'])
                ->whereKey($scheduleTemplate->id)
                ->where('student_id', $studentId)
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
                'schedule_weekday_snapshot' => $this->scheduleWeekdayLabel($ownedScheduleTemplate->weekday),
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

    public function pause(
        PauseScheduleRunRequest $request,
        ScheduleRun $scheduleRun,
        AutomaticObserveTheTimeViolationService $automaticViolationService,
    ): RedirectResponse {
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

        $result = DB::transaction(function () use ($request, $studentId, $scheduleRun, $automaticViolationService) {
            $student = Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();
            $automaticViolationService->evaluate($student);

            if ($blockingMessage = $this->blockingViolationMessage($student)) {
                return [
                    'success' => false,
                    'message' => $blockingMessage,
                ];
            }

            $taskTemplate = TaskTemplate::query()
                ->whereKey((int) $request->input('task_template_id'))
                ->firstOrFail();

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
                $durationSeconds = (int) max(
                    0,
                    (int) ($activeTaskSession->duration_seconds ?? 0)
                        + ($activeTaskSession->started_at?->diffInSeconds($endedAt) ?? 0),
                );

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
                'task_template_id' => $taskTemplate->id,
                'status' => 'active',
                'task_title_snapshot' => $taskTemplate->title,
                'task_summary_snapshot' => $taskTemplate->summary,
                'task_instructions_snapshot' => $taskTemplate->instructions,
                'assignment_notes_snapshot' => $request->input('notes'),
                'planned_duration_minutes' => $taskTemplate->default_duration_minutes,
                'duration_seconds' => 0,
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

    public function resume(
        ResumeScheduleRunRequest $request,
        ScheduleRun $scheduleRun,
        AutomaticObserveTheTimeViolationService $automaticViolationService,
    ): RedirectResponse {
        $studentId = $request->user()?->student?->id;

        if (! $studentId) {
            abort(403);
        }

        $result = DB::transaction(function () use ($request, $studentId, $scheduleRun, $automaticViolationService) {
            $student = Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();
            $automaticViolationService->evaluate($student);

            if ($blockingMessage = $this->blockingViolationMessage($student)) {
                return [
                    'success' => false,
                    'message' => $blockingMessage,
                ];
            }

            $ownedScheduleRun = ScheduleRun::query()
                ->with('blocks')
                ->whereKey($scheduleRun->id)
                ->where('student_id', $studentId)
                ->where('status', 'paused')
                ->lockForUpdate()
                ->firstOrFail();

            $activeTaskSession = TaskSession::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($activeTaskSession && $activeTaskSession->schedule_run_id !== null) {
                return [
                    'success' => false,
                    'message' => 'Остановите текущий таймер перед возобновлением расписания.',
                ];
            }

            $completedAdHocTaskTitle = null;

            if ($activeTaskSession) {
                $endedAt = now();
                $durationSeconds = (int) max(
                    0,
                    (int) ($activeTaskSession->duration_seconds ?? 0)
                        + ($activeTaskSession->started_at?->diffInSeconds($endedAt) ?? 0),
                );

                $activeTaskSession->update([
                    'status' => 'completed',
                    'ended_at' => $endedAt,
                    'duration_seconds' => $durationSeconds,
                    'completion_notes' => 'Автоматически завершено при возобновлении расписания.',
                    'stopped_by_user_id' => $request->user()->id,
                ]);

                $completedAdHocTaskTitle = $activeTaskSession->task_title_snapshot;
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

            $latestPausedTaskSession = TaskSession::query()
                ->where('student_id', $studentId)
                ->where('schedule_run_id', $ownedScheduleRun->id)
                ->where('schedule_run_block_id', $pausedBlock->id)
                ->where('status', 'paused')
                ->latest('ended_at')
                ->lockForUpdate()
                ->first();

            $startedAt = now();
            $elapsedBeforePauseSeconds = max(0, (int) ($latestPausedTaskSession?->duration_seconds ?? 0));

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
                'duration_seconds' => $elapsedBeforePauseSeconds,
                'started_at' => $startedAt,
                'started_by_user_id' => $request->user()->id,
            ]);

            return [
                'success' => true,
                'message' => $completedAdHocTaskTitle !== null
                    ? "Свой таймер {$completedAdHocTaskTitle} завершён. Сессия задания {$pausedBlock->task_title_snapshot} возобновлена."
                    : "Сессия задания {$pausedBlock->task_title_snapshot} возобновлена.",
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

        return "Есть открытое нарушение: {$violation->rule_title_snapshot}{$timeSuffix}. Закройте его у наставника, прежде чем продолжать расписание.";
    }
}
