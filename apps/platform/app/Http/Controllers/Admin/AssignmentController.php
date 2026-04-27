<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAssignment;
use App\Services\StudentDeviceMessageDisplayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedStudentId = $request->integer('student_id') ?: null;

        return Inertia::render('Admin/Assignments/Index', [
            'students' => Student::query()
                ->with('user')
                ->orderBy('display_name')
                ->get()
                ->map(fn (Student $student) => [
                    'id' => $student->id,
                    'display_name' => $student->display_name,
                    'username' => $student->user->username,
                ]),
            'selectedStudentId' => $selectedStudentId,
            'assignments' => StudentAssignment::query()
                ->with(['student.user', 'creator'])
                ->when($selectedStudentId, fn ($query) => $query->where('student_id', $selectedStudentId))
                ->orderByRaw("case status when 'unread' then 0 when 'viewed' then 1 when 'in_progress' then 2 when 'handed_in' then 3 when 'completed' then 4 else 5 end")
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (StudentAssignment $assignment) => $this->payload($assignment)),
        ]);
    }

    public function show(Student $student): Response
    {
        $student->loadMissing('user');

        return Inertia::render('Admin/Assignments/Show', [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
            ],
            'assignments' => StudentAssignment::query()
                ->with('creator')
                ->where('student_id', $student->id)
                ->orderByRaw("case status when 'unread' then 0 when 'viewed' then 1 when 'in_progress' then 2 when 'handed_in' then 3 when 'completed' then 4 else 5 end")
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (StudentAssignment $assignment) => $this->payload($assignment)),
        ]);
    }

    public function store(Request $request, StudentDeviceMessageDisplayService $studentDeviceMessageDisplayService): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'required_without:image'],
            'image' => ['nullable', 'image', 'max:8192', 'required_without:body'],
        ]);

        $image = $request->file('image');
        $path = $image?->store("assignments/{$data['student_id']}", 'local');
        $trimmedBody = trim((string) ($data['body'] ?? ''));

        $assignment = StudentAssignment::create([
            'student_id' => $data['student_id'],
            'created_by_user_id' => $request->user()->id,
            'title' => 'Assignment',
            'body' => $trimmedBody !== '' ? $trimmedBody : null,
            'attachment_disk' => $path ? 'local' : null,
            'attachment_path' => $path,
            'attachment_name' => $image?->getClientOriginalName(),
            'attachment_mime' => $image?->getClientMimeType(),
            'attachment_size' => $image?->getSize(),
            'status' => 'unread',
        ]);

        $studentDeviceMessageDisplayService->queueAssignment($assignment, $request->user());

        if ($request->boolean('return_to_student')) {
            return redirect()
                ->route('admin.students.assignments.show', $assignment->student_id)
                ->with('success', 'Assignment created.');
        }

        if ($request->boolean('return_to_dashboard')) {
            return redirect()
                ->route('admin.dashboard')
                ->with('success', 'Assignment created.');
        }

        return redirect()
            ->route('admin.assignments.index', ['student_id' => $assignment->student_id])
            ->with('success', 'Assignment created.');
    }

    public function destroy(StudentAssignment $studentAssignment): RedirectResponse
    {
        $studentId = $studentAssignment->student_id;

        if ($studentAssignment->attachment_path) {
            Storage::disk($studentAssignment->attachment_disk ?? 'local')->delete($studentAssignment->attachment_path);
        }

        $studentAssignment->delete();

        return redirect()
            ->route('admin.assignments.index', ['student_id' => $studentId])
            ->with('success', 'Assignment deleted.');
    }

    public function complete(StudentAssignment $studentAssignment): RedirectResponse
    {
        $studentAssignment->markCompleted();

        return redirect()
            ->back()
            ->with('success', 'Assignment marked completed.');
    }

    public function incomplete(StudentAssignment $studentAssignment): RedirectResponse
    {
        $studentAssignment->markIncomplete();

        return redirect()
            ->back()
            ->with('success', 'Assignment marked incomplete.');
    }

    private function payload(StudentAssignment $assignment): array
    {
        $assignment->loadMissing(['student.user', 'creator']);

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
            'student' => [
                'id' => $assignment->student->id,
                'display_name' => $assignment->student->display_name,
                'username' => $assignment->student->user->username,
            ],
            'delete_url' => route('admin.assignments.destroy', $assignment),
            'complete_url' => $assignment->status === 'handed_in'
                ? route('admin.assignments.complete', $assignment)
                : null,
            'incomplete_url' => in_array($assignment->status, ['handed_in', 'completed'], true)
                ? route('admin.assignments.incomplete', $assignment)
                : null,
            'student_view_url' => route('admin.students.assignments.show', $assignment->student),
        ];
    }
}
