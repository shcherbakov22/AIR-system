<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskAssignmentRequest;
use App\Http\Requests\Admin\UpdateTaskAssignmentRequest;
use App\Models\Student;
use App\Models\TaskAssignment;
use App\Models\TaskTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaskAssignmentController extends Controller
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
                'is_active' => $student->user->is_active,
            ])
            ->all();
    }

    protected function taskTemplateOptions(?TaskAssignment $taskAssignment = null): array
    {
        return TaskTemplate::query()
            ->orderBy('title')
            ->get()
            ->map(fn (TaskTemplate $taskTemplate) => [
                'id' => $taskTemplate->id,
                'title' => $taskTemplate->title,
                'default_duration_minutes' => $taskTemplate->default_duration_minutes,
            ])
            ->all();
    }

    protected function toPayload(TaskAssignment $taskAssignment): array
    {
        $taskAssignment->loadMissing(['student.user', 'taskTemplate']);

        return [
            'id' => $taskAssignment->id,
            'status' => $taskAssignment->status,
            'due_on' => $taskAssignment->due_on?->toDateString(),
            'notes' => $taskAssignment->notes,
            'student' => [
                'id' => $taskAssignment->student->id,
                'display_name' => $taskAssignment->student->display_name,
                'username' => $taskAssignment->student->user->username,
            ],
            'task_template' => [
                'id' => $taskAssignment->taskTemplate->id,
                'title' => $taskAssignment->taskTemplate->title,
                'default_duration_minutes' => $taskAssignment->taskTemplate->default_duration_minutes,
                'summary' => $taskAssignment->taskTemplate->summary,
            ],
            'created_at' => $taskAssignment->created_at?->toDateTimeString(),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/TaskAssignments/Index', [
            'taskAssignments' => TaskAssignment::query()
                ->with(['student.user', 'taskTemplate'])
                ->latest()
                ->get()
                ->map(fn (TaskAssignment $taskAssignment) => $this->toPayload($taskAssignment)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TaskAssignments/Create', [
            'students' => $this->studentOptions(),
            'taskTemplates' => $this->taskTemplateOptions(),
        ]);
    }

    public function store(StoreTaskAssignmentRequest $request): RedirectResponse
    {
        $taskAssignment = TaskAssignment::create([
            'student_id' => (int) $request->input('student_id'),
            'task_template_id' => (int) $request->input('task_template_id'),
            'assigned_by_user_id' => $request->user()->id,
            'status' => $request->string('status')->toString(),
            'due_on' => $request->input('due_on'),
            'notes' => $request->input('notes'),
        ]);

        $taskAssignment->loadMissing('taskTemplate');

        return redirect()
            ->route('admin.task-assignments.index')
            ->with('success', "Task assignment {$taskAssignment->taskTemplate->title} created.");
    }

    public function edit(TaskAssignment $taskAssignment): Response
    {
        return Inertia::render('Admin/TaskAssignments/Edit', [
            'taskAssignment' => [
                'id' => $taskAssignment->id,
                'student_id' => (string) $taskAssignment->student_id,
                'task_template_id' => (string) $taskAssignment->task_template_id,
                'status' => $taskAssignment->status,
                'due_on' => $taskAssignment->due_on?->toDateString() ?? '',
                'notes' => $taskAssignment->notes ?? '',
            ],
            'students' => $this->studentOptions(),
            'taskTemplates' => $this->taskTemplateOptions($taskAssignment),
        ]);
    }

    public function update(UpdateTaskAssignmentRequest $request, TaskAssignment $taskAssignment): RedirectResponse
    {
        $taskAssignment->update([
            'student_id' => (int) $request->input('student_id'),
            'task_template_id' => (int) $request->input('task_template_id'),
            'status' => $request->string('status')->toString(),
            'due_on' => $request->input('due_on'),
            'notes' => $request->input('notes'),
        ]);

        $taskAssignment->loadMissing('taskTemplate');

        return redirect()
            ->route('admin.task-assignments.index')
            ->with('success', "Task assignment {$taskAssignment->taskTemplate->title} updated.");
    }
}
