<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaskSession;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): Response
    {
        $activeTaskSessions = TaskSession::query()
            ->with(['student.user', 'scheduleRun', 'scheduleRunBlock'])
            ->where('status', 'active')
            ->latest('started_at')
            ->get()
            ->map(fn (TaskSession $taskSession) => [
                'id' => $taskSession->id,
                'task_title' => $taskSession->task_title_snapshot,
                'started_at' => $taskSession->started_at?->toIso8601String(),
                'started_at_label' => $taskSession->started_at?->format('d M, H:i'),
                'duration_seconds' => $taskSession->duration_seconds ?? 0,
                'planned_duration_minutes' => $taskSession->planned_duration_minutes,
                'source_type' => $taskSession->schedule_run_id
                    ? 'schedule'
                    : ($taskSession->task_assignment_id ? 'assignment' : 'custom'),
                'student' => [
                    'id' => $taskSession->student->id,
                    'display_name' => $taskSession->student->display_name,
                    'username' => $taskSession->student->user->username,
                    'status' => $taskSession->student->status,
                ],
                'schedule_run' => $taskSession->scheduleRun
                    ? [
                        'id' => $taskSession->scheduleRun->id,
                        'name' => $taskSession->scheduleRun->schedule_name_snapshot,
                    ]
                    : null,
                'schedule_run_block' => $taskSession->scheduleRunBlock
                    ? [
                        'position' => $taskSession->scheduleRunBlock->position,
                    ]
                    : null,
            ])
            ->all();

        return Inertia::render('Admin/Dashboard', [
            'serverNow' => now()->toIso8601String(),
            'activeTaskSessions' => $activeTaskSessions,
        ]);
    }
}
