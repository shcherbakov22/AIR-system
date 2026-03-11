<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRuleDefinitionRequest;
use App\Http\Requests\Admin\UpdateRuleDefinitionRequest;
use App\Models\RuleDefinition;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RuleDefinitionController extends Controller
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

    protected function toPayload(RuleDefinition $ruleDefinition): array
    {
        $ruleDefinition->loadMissing('student.user');

        return [
            'id' => $ruleDefinition->id,
            'title' => $ruleDefinition->title,
            'description' => $ruleDefinition->description,
            'scope' => $ruleDefinition->scope,
            'is_active' => $ruleDefinition->is_active,
            'student' => $ruleDefinition->student
                ? [
                    'id' => $ruleDefinition->student->id,
                    'display_name' => $ruleDefinition->student->display_name,
                    'username' => $ruleDefinition->student->user->username,
                ]
                : null,
            'created_at' => $ruleDefinition->created_at?->toDateTimeString(),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/RuleDefinitions/Index', [
            'ruleDefinitions' => RuleDefinition::query()
                ->with('student.user')
                ->orderByDesc('is_active')
                ->orderBy('scope')
                ->orderBy('title')
                ->get()
                ->map(fn (RuleDefinition $ruleDefinition) => $this->toPayload($ruleDefinition)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/RuleDefinitions/Create', [
            'students' => $this->studentOptions(),
        ]);
    }

    public function edit(RuleDefinition $ruleDefinition): Response
    {
        return Inertia::render('Admin/RuleDefinitions/Edit', [
            'ruleDefinition' => [
                'id' => $ruleDefinition->id,
                'title' => $ruleDefinition->title,
                'description' => $ruleDefinition->description ?? '',
                'scope' => $ruleDefinition->scope,
                'student_id' => $ruleDefinition->student_id ? (string) $ruleDefinition->student_id : '',
                'is_active' => $ruleDefinition->is_active,
            ],
            'students' => $this->studentOptions(),
        ]);
    }

    public function store(StoreRuleDefinitionRequest $request): RedirectResponse
    {
        $ruleDefinition = RuleDefinition::create([
            'title' => $request->string('title')->toString(),
            'description' => $request->input('description'),
            'scope' => $request->string('scope')->toString(),
            'student_id' => $request->input('student_id'),
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
            'scope' => $request->string('scope')->toString(),
            'student_id' => $request->input('student_id'),
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
