<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRuleDefinitionRequest;
use App\Http\Requests\Admin\UpdateRuleDefinitionRequest;
use App\Models\RuleDefinition;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RuleDefinitionController extends Controller
{
    protected function toPayload(RuleDefinition $ruleDefinition): array
    {
        return [
            'id' => $ruleDefinition->id,
            'title' => $ruleDefinition->title,
            'description' => $ruleDefinition->description,
            'is_active' => $ruleDefinition->is_active,
            'created_at' => $ruleDefinition->created_at?->toDateTimeString(),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/RuleDefinitions/Index', [
            'ruleDefinitions' => RuleDefinition::query()
                ->orderByDesc('is_active')
                ->orderBy('title')
                ->get()
                ->map(fn (RuleDefinition $ruleDefinition) => $this->toPayload($ruleDefinition)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/RuleDefinitions/Create');
    }

    public function edit(RuleDefinition $ruleDefinition): Response
    {
        return Inertia::render('Admin/RuleDefinitions/Edit', [
            'ruleDefinition' => [
                'id' => $ruleDefinition->id,
                'title' => $ruleDefinition->title,
                'description' => $ruleDefinition->description ?? '',
                'is_active' => $ruleDefinition->is_active,
            ],
        ]);
    }

    public function store(StoreRuleDefinitionRequest $request): RedirectResponse
    {
        $ruleDefinition = RuleDefinition::create([
            'title' => $request->string('title')->toString(),
            'description' => $request->input('description'),
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => $request->boolean('is_active'),
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.rule-definitions.index')
            ->with('success', "Rule {$ruleDefinition->title} created.");
    }

    public function update(UpdateRuleDefinitionRequest $request, RuleDefinition $ruleDefinition): RedirectResponse
    {
        $ruleDefinition->update([
            'title' => $request->string('title')->toString(),
            'description' => $request->input('description'),
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.rule-definitions.index')
            ->with('success', "Rule {$ruleDefinition->fresh()->title} updated.");
    }

    public function destroy(RuleDefinition $ruleDefinition): RedirectResponse
    {
        $ruleDefinitionTitle = $ruleDefinition->title;

        $ruleDefinition->delete();

        return redirect()
            ->route('admin.rule-definitions.index')
            ->with('success', 'Rule '.$ruleDefinitionTitle.' deleted.');
    }
}
