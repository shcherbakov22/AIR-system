<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreAiOverseerDecisionRequest;
use App\Models\AiOverseerDecision;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Violation;
use App\Services\AiOverseerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiOverseerDecisionController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user()?->student;

        if (! $student) {
            abort(403);
        }

        $decisions = AiOverseerDecision::query()
            ->with(['violation', 'scheduleRunBlock', 'messages'])
            ->where('student_id', $student->id)
            ->latest('created_at')
            ->limit(25)
            ->get();

        $openViolations = Violation::query()
            ->where('student_id', $student->id)
            ->where('status', 'open')
            ->latest('occurred_at')
            ->limit(20)
            ->get();

        $activeScheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->whereIn('status', ['active', 'paused'])
            ->latest('started_at')
            ->first();

        $selectedViolationId = $request->integer('violation_id') ?: null;
        $selectedBlockId = $request->integer('schedule_run_block_id') ?: null;
        $selectedDecisionId = $request->integer('ai_overseer_decision_id') ?: null;

        return Inertia::render('Student/AiOverseer/Index', [
            'decisions' => $decisions
                ->map(fn (AiOverseerDecision $decision) => [
                    'id' => $decision->id,
                    'request_type' => $decision->request_type,
                    'status' => $decision->status,
                    'decision' => $decision->decision,
                    'confidence' => $decision->confidence,
                    'student_reason' => $decision->student_reason,
                    'student_message' => $decision->student_message,
                    'reason' => $decision->reason,
                    'mentor_summary' => $decision->mentor_summary,
                    'target_label' => $decision->violation?->rule_title_snapshot
                        ?? $decision->scheduleRunBlock?->task_title_snapshot,
                    'created_at_label' => $decision->created_at?->locale(app()->getLocale())->translatedFormat('d M, H:i'),
                    'messages' => $decision->messages
                        ->map(fn ($message) => [
                            'id' => $message->id,
                            'sender' => $message->sender,
                            'body' => $message->body,
                            'is_final_decision' => $message->is_final_decision,
                            'created_at_label' => $message->created_at?->locale(app()->getLocale())->translatedFormat('d M, H:i'),
                        ])
                        ->all(),
                ])
                ->all(),
            'openViolations' => $openViolations
                ->map(fn (Violation $violation) => [
                    'id' => $violation->id,
                    'rule_title' => $violation->rule_title_snapshot,
                    'push_up_count' => $violation->effectivePenaltyUnits(),
                    'occurred_at_label' => $violation->occurred_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                ])
                ->all(),
            'activeScheduleRun' => $activeScheduleRun
                ? [
                    'id' => $activeScheduleRun->id,
                    'status' => $activeScheduleRun->status,
                    'schedule_name' => $activeScheduleRun->schedule_name_snapshot,
                    'blocks' => $activeScheduleRun->blocks
                        ->whereIn('status', ['pending', 'paused'])
                        ->sortBy('position')
                        ->values()
                        ->map(fn (ScheduleRunBlock $block) => [
                            'id' => $block->id,
                            'position' => $block->position,
                            'status' => $block->status,
                            'task_title' => $block->task_title_snapshot,
                            'start_time' => $block->start_time_snapshot,
                            'duration_minutes' => $block->duration_minutes_snapshot,
                        ])
                        ->all(),
                ]
                : null,
            'selectedTarget' => [
                'ai_overseer_decision_id' => $selectedDecisionId,
                'violation_id' => $selectedViolationId,
                'schedule_run_block_id' => $selectedBlockId,
            ],
        ]);
    }

    public function store(
        StoreAiOverseerDecisionRequest $request,
        AiOverseerService $aiOverseerService,
    ): RedirectResponse {
        $student = $request->user()?->student;

        if (! $student) {
            abort(403);
        }

        $decision = $aiOverseerService->decide(
            $student,
            $request->user(),
            $request->validated(),
        );

        $message = $decision->student_message ?: match ($decision->status) {
            'approved' => 'Approved.',
            'denied' => 'Denied.',
            'conversation' => 'AI replied.',
            default => 'Sent to mentor for review.',
        };

        $redirectParameters = ($request->input('intent') === 'chat' || $request->filled('ai_overseer_decision_id'))
            ? ['ai_overseer_decision_id' => $decision->id]
            : [];

        return redirect()
            ->route('student.ai-overseer-decisions.index', $redirectParameters)
            ->with($decision->status === 'denied' ? 'error' : 'success', $message);
    }
}
