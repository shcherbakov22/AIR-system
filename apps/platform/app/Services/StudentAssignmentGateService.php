<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAssignment;

class StudentAssignmentGateService
{
    public function unreadAssignments(Student $student)
    {
        return StudentAssignment::query()
            ->with('creator')
            ->where('student_id', $student->id)
            ->where('status', 'unread')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function payload(Student $student): array
    {
        $unreadAssignments = $this->unreadAssignments($student);
        $latestUnread = $unreadAssignments->last();

        return [
            'has_unread' => $unreadAssignments->isNotEmpty(),
            'unread_count' => $unreadAssignments->count(),
            'latest_unread_assignment' => $latestUnread ? [
                'id' => $latestUnread->id,
                'created_at_label' => $latestUnread->created_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                'creator_name' => $latestUnread->creator?->name ?: 'Mentor',
            ] : null,
        ];
    }

    public function blockingMessage(Student $student): ?string
    {
        $payload = $this->payload($student);

        if (! $payload['has_unread']) {
            return null;
        }

        $count = $payload['unread_count'];

        return $count === 1
            ? 'Open Assignments and review the unread assignment before continuing.'
            : "Open Assignments and review the {$count} unread assignments before continuing.";
    }

    public function markAllViewed(Student $student): void
    {
        StudentAssignment::query()
            ->where('student_id', $student->id)
            ->where('status', 'unread')
            ->update([
                'status' => 'viewed',
                'viewed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
