<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskTemplateRequest;
use App\Http\Requests\Admin\UpdateTaskTemplateRequest;
use App\Models\TaskTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaskTemplateController extends Controller
{
    protected function toPayload(TaskTemplate $taskTemplate): array
    {
        return [
            'id' => $taskTemplate->id,
            'title' => $taskTemplate->title,
            'instructions' => $taskTemplate->instructions,
            'default_duration_minutes' => $taskTemplate->default_duration_minutes,
            'created_at' => $taskTemplate->created_at?->toDateTimeString(),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/TaskTemplates/Index', [
            'taskTemplates' => TaskTemplate::query()
                ->orderBy('title')
                ->get()
                ->map(fn (TaskTemplate $taskTemplate) => $this->toPayload($taskTemplate)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TaskTemplates/Create');
    }

    public function edit(TaskTemplate $taskTemplate): Response
    {
        return Inertia::render('Admin/TaskTemplates/Edit', [
            'taskTemplate' => $this->toPayload($taskTemplate),
        ]);
    }

    public function store(StoreTaskTemplateRequest $request): RedirectResponse
    {
        $taskTemplate = TaskTemplate::create([
            'title' => $request->string('title')->toString(),
            'summary' => null,
            'instructions' => $request->input('instructions'),
            'default_duration_minutes' => (int) $request->input('default_duration_minutes'),
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.task-templates.index')
            ->with('success', "Шаблон задания {$taskTemplate->title} создан.");
    }

    public function update(UpdateTaskTemplateRequest $request, TaskTemplate $taskTemplate): RedirectResponse
    {
        $taskTemplate->update([
            'title' => $request->string('title')->toString(),
            'summary' => null,
            'instructions' => $request->input('instructions'),
            'default_duration_minutes' => (int) $request->input('default_duration_minutes'),
        ]);

        return redirect()
            ->route('admin.task-templates.index')
            ->with('success', "Шаблон задания {$taskTemplate->fresh()->title} обновлён.");
    }

    public function destroy(TaskTemplate $taskTemplate): RedirectResponse
    {
        $taskTemplateTitle = $taskTemplate->title;

        $taskTemplate->delete();

        return redirect()
            ->route('admin.task-templates.index')
            ->with('success', "Шаблон задания {$taskTemplateTitle} удалён.");
    }
}
