<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Student;
use App\Models\StudentAssignment;
use App\Models\User;
use Illuminate\Support\Str;

class StudentDeviceMessageDisplayService
{
    public function queueChatMessage(ChatMessage $message, Student $student, ?User $requestedBy = null): void
    {
        if (! $message->isChat()) {
            return;
        }

        $bodyParts = [];
        $trimmedBody = trim((string) $message->body);

        if ($trimmedBody !== '') {
            $bodyParts[] = $trimmedBody;
        }

        if ($message->hasAttachment()) {
            $bodyParts[] = 'Attachment: '.($message->attachment_name ?: 'file');
        }

        $displayBody = Str::limit(implode("\n\n", $bodyParts), 900);
        if ($displayBody === '') {
            return;
        }

        $title = 'Message from mentor';
        if ($requestedBy && filled($requestedBy->name)) {
            $title = 'Message from '.trim((string) $requestedBy->name);
        }

        $this->queueForStudent($student, $requestedBy, [
            'title' => $title,
            'body' => $displayBody,
            'display_seconds' => 20,
            'chat_message_id' => $message->id,
        ]);
    }

    public function queueAssignment(StudentAssignment $assignment, ?User $requestedBy = null): void
    {
        $assignment->loadMissing('student');

        $bodyParts = [];
        $trimmedBody = trim((string) $assignment->body);

        if ($trimmedBody !== '') {
            $bodyParts[] = $trimmedBody;
        }

        $displayBody = Str::limit(implode("\n\n", $bodyParts), 900);
        if ($displayBody === '') {
            return;
        }

        $this->queueForStudent($assignment->student, $requestedBy, [
            'title' => 'New assignment',
            'body' => $displayBody,
            'display_seconds' => 20,
            'student_assignment_id' => $assignment->id,
        ]);
    }

    public function queueAnnouncement(ChatMessage $message, ?User $requestedBy = null): void
    {
        if (! $message->isAnnouncement()) {
            return;
        }

        $bodyParts = [];
        $trimmedBody = trim((string) $message->body);

        if ($trimmedBody !== '') {
            $bodyParts[] = $trimmedBody;
        }

        if ($message->hasAttachment()) {
            $bodyParts[] = 'Attachment: '.($message->attachment_name ?: 'file');
        }

        $displayBody = Str::limit(implode("\n\n", $bodyParts), 900);
        if ($displayBody === '') {
            return;
        }

        Student::query()
            ->where('status', 'active')
            ->each(function (Student $student) use ($requestedBy, $displayBody, $message): void {
                $this->queueForStudent($student, $requestedBy, [
                    'title' => 'Announcement',
                    'body' => $displayBody,
                    'display_seconds' => 20,
                    'chat_message_id' => $message->id,
                ]);
            });
    }

    private function queueForStudent(Student $student, ?User $requestedBy, array $payload): void
    {
        $student->devices()
            ->whereNull('revoked_at')
            ->get()
            ->each(function ($device) use ($requestedBy, $payload): void {
                $device->commands()->create([
                    'requested_by_user_id' => $requestedBy?->id,
                    'command_type' => 'show_message',
                    'status' => 'pending',
                    'payload' => $payload,
                    'requested_at' => now(),
                ]);
            });
    }
}
