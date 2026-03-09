<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskAssignment;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'students_total' => Student::count(),
                'students_active' => Student::where('status', 'active')->count(),
                'admins_total' => User::where('role', 'admin')->count(),
                'task_templates_total' => TaskTemplate::count(),
                'task_assignments_total' => TaskAssignment::count(),
                'task_sessions_total' => TaskSession::count(),
                'task_sessions_active' => TaskSession::where('status', 'active')->count(),
                'schedule_templates_total' => ScheduleTemplate::count(),
            ],
        ]);
    }
}
