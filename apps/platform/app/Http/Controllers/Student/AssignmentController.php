<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentAssignment;
use App\Services\StudentAssignmentGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(Request $request, StudentAssignmentGateService $assignmentGateService): Response
    {
        $student = $request->user()->student;

        abort_unless($student !== null, 404);

        $assignmentGateService->markAllViewed($student);

        $assignments = StudentAssignment::query()
            ->with('creator')
            ->where('student_id', $student->id)
            ->orderByRaw("case status when 'unread' then 0 when 'viewed' then 1 when 'in_progress' then 2 when 'handed_in' then 3 when 'completed' then 4 else 5 end")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Student/Assignments/Index', [
            'assignments' => $assignments->map(fn (StudentAssignment $assignment) => $this->payload($assignment)),
        ]);
    }

    public function start(Request $request, StudentAssignment $studentAssignment): RedirectResponse
    {
        $student = $request->user()->student;

        abort_unless($student && $studentAssignment->student_id === $student->id, 404);

        $studentAssignment->markViewed();
        $studentAssignment->refresh();
        $studentAssignment->markInProgress();

        return redirect()
            ->route('student.assignments.index')
            ->with('success', 'Assignment marked in progress.');
    }

    public function handIn(Request $request, StudentAssignment $studentAssignment): RedirectResponse
    {
        $student = $request->user()->student;

        abort_unless($student && $studentAssignment->student_id === $student->id, 404);

        $studentAssignment->markHandedIn();

        return redirect()
            ->route('student.assignments.index')
            ->with('success', 'Assignment handed in.');
    }

    private function payload(StudentAssignment $assignment): array
    {
        $assignment->loadMissing('creator');

        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'body' => $assignment->body,
            'attachment' => $assignment->hasAttachment() ? [
                'name' => $assignment->attachment_name,
                'mime' => $assignment->attachment_mime,
                'size' => $assignment->attachment_size,
                'url' => route('student-assignments.attachment.show', $assignment),
            ] : null,
            'status' => $assignment->status,
            'created_at_label' => $assignment->created_at?->format('j M, H:i'),
            'viewed_at_label' => $assignment->viewed_at?->format('j M, H:i'),
            'started_at_label' => $assignment->started_at?->format('j M, H:i'),
            'completed_at_label' => $assignment->completed_at?->format('j M, H:i'),
            'creator_name' => $assignment->creator?->name ?: 'Mentor',
            'start_url' => $assignment->status === 'viewed'
                ? route('student.assignments.start', $assignment)
                : null,
            'hand_in_url' => $assignment->status === 'in_progress'
                ? route('student.assignments.hand-in', $assignment)
                : null,
        ];
    }
}
