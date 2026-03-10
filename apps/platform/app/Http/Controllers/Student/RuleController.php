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
            ->where(function ($query) use ($student) {
                $query->where('scope', 'global');

                if ($student) {
                    $query->orWhere(function ($studentQuery) use ($student) {
                        $studentQuery
                            ->where('scope', 'student')
                            ->where('student_id', $student->id);
                    });
                }
            })
            ->with('student.user')
            ->orderBy('scope')
            ->orderBy('title')
            ->get();

        return Inertia::render('Student/Rules/Index', [
            'student' => [
                'display_name' => $student?->display_name ?? $request->user()->name,
                'status' => $student?->status ?? 'pending',
            ],
            'ruleSummary' => [
                'total' => $rules->count(),
                'global' => $rules->where('scope', 'global')->count(),
                'personal' => $rules->where('scope', 'student')->count(),
            ],
            'rules' => $rules
                ->map(fn (RuleDefinition $ruleDefinition) => [
                    'id' => $ruleDefinition->id,
                    'title' => $ruleDefinition->title,
                    'description' => $ruleDefinition->description,
                    'scope' => $ruleDefinition->scope,
                    'student' => $ruleDefinition->student
                        ? [
                            'id' => $ruleDefinition->student->id,
                            'display_name' => $ruleDefinition->student->display_name,
                            'username' => $ruleDefinition->student->user->username,
                        ]
                        : null,
                    'created_at_label' => $ruleDefinition->created_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                ])
                ->all(),
        ]);
    }
}
