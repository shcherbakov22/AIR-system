<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\RuleDefinition;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RuleController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user()
            ->loadMissing('student.user')
            ->student;

        $rules = RuleDefinition::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        return Inertia::render('Student/Rules/Index', [
            'student' => [
                'display_name' => $student?->display_name ?? $request->user()->name,
                'status' => $student?->status ?? 'pending',
            ],
            'ruleSummary' => [
                'total' => $rules->count(),
            ],
            'rules' => $rules
                ->map(fn (RuleDefinition $ruleDefinition) => [
                    'id' => $ruleDefinition->id,
                    'title' => $ruleDefinition->title,
                ])
                ->all(),
        ]);
    }
}
