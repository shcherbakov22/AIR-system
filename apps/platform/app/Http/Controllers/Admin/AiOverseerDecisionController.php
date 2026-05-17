<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewAiOverseerDecisionRequest;
use App\Models\AiOverseerDecision;
use App\Services\AiOverseerService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AiOverseerDecisionController extends Controller
{
    public function index(): Response
    {
        $decisions = AiOverseerDecision::query()
            ->with(['student.user', 'requestedBy', 'reviewedBy', 'violation', 'scheduleRunBlock', 'mentorChatMessage'])
            ->latest('created_at')
            ->limit(80)
            ->get()
            ->map(fn (AiOverseerDecision $decision) => $this->toPayload($decision));

        return Inertia::render('Admin/AiOverseer/Index', [
            'decisions' => $decisions,
        ]);
    }

    public function update(
        ReviewAiOverseerDecisionRequest $request,
        AiOverseerDecision $aiOverseerDecision,
        AiOverseerService $aiOverseer,
    ): RedirectResponse {
        $aiOverseer->review(
            $aiOverseerDecision,
            $request->user(),
            $request->string('status')->toString(),
            $request->input('notes'),
        );

        return redirect()
            ->route('admin.ai-overseer-decisions.index')
            ->with('success', 'AI overseer request updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(AiOverseerDecision $decision): array
    {
        return [
            'id' => $decision->id,
            'request_type' => $decision->request_type,
            'status' => $decision->status,
            'decision' => $decision->decision,
            'confidence' => $decision->confidence,
            'student_reason' => $decision->student_reason,
            'student_message' => $decision->student_message,
            'mentor_summary' => $decision->mentor_summary,
            'reason' => $decision->reason,
            'action_taken' => $decision->action_taken,
            'mentor_notified_at_label' => $decision->mentor_notified_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
            'created_at_label' => $decision->created_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
            'student' => [
                'id' => $decision->student->id,
                'display_name' => $decision->student->display_name,
                'username' => $decision->student->user->username,
            ],
            'target' => [
                'violation' => $decision->violation
                    ? [
                        'id' => $decision->violation->id,
                        'rule_title' => $decision->violation->rule_title_snapshot,
                        'status' => $decision->violation->status,
                    ]
                    : null,
                'schedule_run_block' => $decision->scheduleRunBlock
                    ? [
                        'id' => $decision->scheduleRunBlock->id,
                        'task_title' => $decision->scheduleRunBlock->task_title_snapshot,
                        'status' => $decision->scheduleRunBlock->status,
                    ]
                    : null,
            ],
        ];
    }
}
