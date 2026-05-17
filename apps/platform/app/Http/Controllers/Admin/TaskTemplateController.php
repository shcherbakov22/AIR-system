<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskTemplateRequest;
use App\Http\Requests\Admin\UpdateTaskTemplateRequest;
use App\Models\BrowserPolicyRule;
use App\Models\TaskTemplate;
use App\Services\BrowserAccountabilityPolicyService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaskTemplateController extends Controller
{
    protected function toPayload(TaskTemplate $taskTemplate): array
    {
        $taskTemplate->loadMissing('browserPolicyRules');

        return [
            'id' => $taskTemplate->id,
            'title' => $taskTemplate->title,
            'instructions' => $taskTemplate->instructions,
            'default_duration_minutes' => $taskTemplate->default_duration_minutes,
            'requires_internet' => $taskTemplate->requires_internet,
            'can_end_early' => $taskTemplate->can_end_early,
            'can_interrupt_schedule' => $taskTemplate->can_interrupt_schedule,
            'browser_allowed_domains' => $taskTemplate->browserPolicyRules
                ->whereNull('student_id')
                ->where('effect', 'allow')
                ->where('match_type', 'domain_tree')
                ->sortBy('value')
                ->values()
                ->map(fn (BrowserPolicyRule $rule) => $rule->value)
                ->all(),
            'created_at' => $taskTemplate->created_at?->toDateTimeString(),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/TaskTemplates/Index', [
            'taskTemplates' => TaskTemplate::query()
                ->orderBy('title')
                ->with('browserPolicyRules')
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

    public function store(
        StoreTaskTemplateRequest $request,
        BrowserAccountabilityPolicyService $browserPolicyService,
    ): RedirectResponse
    {
        $taskTemplate = TaskTemplate::create([
            'title' => $request->string('title')->toString(),
            'summary' => null,
            'instructions' => $request->input('instructions'),
            'default_duration_minutes' => (int) $request->input('default_duration_minutes'),
            'requires_internet' => $request->boolean('requires_internet'),
            'can_end_early' => $request->boolean('can_end_early'),
            'can_interrupt_schedule' => $request->boolean('can_interrupt_schedule'),
            'created_by_user_id' => $request->user()->id,
        ]);

        $browserPolicyService->syncTaskAllowDomains(
            $taskTemplate,
            $request->input('browser_allowed_domains', []),
            $request->user(),
        );

        return redirect()
            ->route('admin.task-templates.index')
            ->with('success', "Task template {$taskTemplate->title} has been created.");
    }

    public function update(
        UpdateTaskTemplateRequest $request,
        TaskTemplate $taskTemplate,
        BrowserAccountabilityPolicyService $browserPolicyService,
    ): RedirectResponse
    {
        $taskTemplate->update([
            'title' => $request->string('title')->toString(),
            'summary' => null,
            'instructions' => $request->input('instructions'),
            'default_duration_minutes' => (int) $request->input('default_duration_minutes'),
            'requires_internet' => $request->boolean('requires_internet'),
            'can_end_early' => $request->boolean('can_end_early'),
            'can_interrupt_schedule' => $request->boolean('can_interrupt_schedule'),
        ]);

        $browserPolicyService->syncTaskAllowDomains(
            $taskTemplate,
            $request->input('browser_allowed_domains', []),
            $request->user(),
        );

        return redirect()
            ->route('admin.task-templates.index')
            ->with('success', "Task template {$taskTemplate->fresh()->title} has been updated.");
    }

    public function destroy(TaskTemplate $taskTemplate): RedirectResponse
    {
        $taskTemplateTitle = $taskTemplate->title;

        $taskTemplate->delete();

        return redirect()
            ->route('admin.task-templates.index')
            ->with('success', "Task template {$taskTemplateTitle} has been deleted.");
    }
}
