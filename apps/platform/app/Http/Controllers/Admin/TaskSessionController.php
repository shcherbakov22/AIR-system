<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TaskSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskSessionController extends Controller
{
    protected function studentOptions(): array
    {
        return Student::query()
            ->with('user')
            ->orderBy('display_name')
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
            ])
            ->all();
    }

    protected function formatDurationLabel(?int $durationSeconds): ?string
    {
        if ($durationSeconds === null) {
            return null;
        }

        if ($durationSeconds < 60) {
            $seconds = max(1, $durationSeconds);

            return $seconds === 1 ? '1 секунда' : "{$seconds} секунд";
        }

        $minutes = (int) ceil($durationSeconds / 60);

        return $minutes === 1 ? '1 минута' : "{$minutes} минут";
    }

    protected function toPayload(TaskSession $taskSession): array
    {
        $taskSession->loadMissing(['student.user', 'taskAssignment', 'taskTemplate', 'scheduleRun', 'scheduleRunBlock']);

        return [
            'id' => $taskSession->id,
            'status' => $taskSession->status,
            'task_title' => $taskSession->task_title_snapshot,
            'task_summary' => $taskSession->task_summary_snapshot,
            'context_notes' => $taskSession->assignment_notes_snapshot,
            'completion_notes' => $taskSession->completion_notes,
            'planned_duration_minutes' => $taskSession->planned_duration_minutes,
            'started_at_label' => $taskSession->started_at?->locale(app()->getLocale())->translatedFormat('d M, H:i'),
            'ended_at_label' => $taskSession->ended_at?->locale(app()->getLocale())->translatedFormat('d M, H:i'),
            'duration_label' => $this->formatDurationLabel($taskSession->duration_seconds),
            'source_type' => $taskSession->schedule_run_id
                ? 'schedule'
                : ($taskSession->task_assignment_id ? 'assignment' : 'ad_hoc'),
            'student' => [
                'id' => $taskSession->student->id,
                'display_name' => $taskSession->student->display_name,
                'username' => $taskSession->student->user->username,
                'status' => $taskSession->student->status,
                'is_active' => $taskSession->student->user->is_active,
            ],
            'task_assignment' => $taskSession->taskAssignment
                ? [
                    'id' => $taskSession->taskAssignment->id,
                    'status' => $taskSession->taskAssignment->status,
                    'due_on' => $taskSession->taskAssignment->due_on?->toDateString(),
                ]
                : null,
            'schedule_run' => $taskSession->scheduleRun
                ? [
                    'id' => $taskSession->scheduleRun->id,
                    'name' => $taskSession->scheduleRun->schedule_name_snapshot,
                    'weekday_label' => $taskSession->scheduleRun->schedule_weekday_snapshot,
                ]
                : null,
            'schedule_run_block' => $taskSession->scheduleRunBlock
                ? [
                    'id' => $taskSession->scheduleRunBlock->id,
                    'position' => $taskSession->scheduleRunBlock->position,
                    'status' => $taskSession->scheduleRunBlock->status,
                ]
                : null,
            'task_template' => $taskSession->taskTemplate
                ? [
                    'id' => $taskSession->taskTemplate->id,
                    'title' => $taskSession->taskTemplate->title,
                ]
                : null,
        ];
    }

    public function index(Request $request): Response
    {
        $studentId = $request->integer('student_id');
        $status = $request->string('status')->toString();
        $allowedStatuses = ['active', 'paused', 'completed'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $taskSessions = TaskSession::query()
            ->with(['student.user', 'taskAssignment', 'taskTemplate', 'scheduleRun', 'scheduleRunBlock'])
            ->when(
                $studentId > 0,
                fn ($query) => $query->where('student_id', $studentId)
            )
            ->when(
                $status !== '',
                fn ($query) => $query->where('status', $status)
            )
            ->latest('started_at')
            ->get()
            ->map(fn (TaskSession $taskSession) => $this->toPayload($taskSession))
            ->all();

        return Inertia::render('Admin/TaskSessions/Index', [
            'taskSessions' => $taskSessions,
            'students' => $this->studentOptions(),
            'filters' => [
                'student_id' => $studentId > 0 ? (string) $studentId : '',
                'status' => $status,
            ],
            'metrics' => [
                'total' => TaskSession::count(),
                'active' => TaskSession::where('status', 'active')->count(),
                'paused' => TaskSession::where('status', 'paused')->count(),
                'completed' => TaskSession::where('status', 'completed')->count(),
            ],
        ]);
    }

    public function destroyAll(Request $request)
    {
        $request->validate([
            'filter' => 'required|in:active,completed,all',
        ]);

        $filter = $request->string('filter')->toString();

        $query = TaskSession::query();

        if ($filter === 'active') {
            $query->where('status', 'active');
        } elseif ($filter === 'completed') {
            $query->where('status', 'completed');
        }

        $count = $query->count();
        $query->delete();

        return back()->with('success', "Удалено сессий: {$count}");
    }
}
