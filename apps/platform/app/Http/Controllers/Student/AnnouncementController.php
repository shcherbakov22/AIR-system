<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Student;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function show(): Response
    {
        $student = request()->user()?->student;

        abort_unless($student, 403);

        return Inertia::render('Student/Announcements/Show', [
            'studentThread' => $this->threadPayload($student),
        ]);
    }

    private function threadPayload(Student $student): array
    {
        $student->loadMissing(['user', 'announcementMessages.sender']);

        return [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
            ],
            'messages' => $student->announcementMessages
                ->sortBy('created_at')
                ->values()
                ->map(fn (ChatMessage $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'created_at_label' => $message->created_at?->format('j M, H:i'),
                    'sent_by_role' => 'mentor',
                    'sent_by_name' => $message->sender?->name ?: 'Mentor',
                    'attachment' => $message->hasAttachment() ? [
                        'name' => $message->attachment_name,
                        'mime' => $message->attachment_mime,
                        'size' => $message->attachment_size,
                        'is_image' => $message->isImage(),
                        'url' => route('chat-messages.attachment.show', $message),
                    ] : null,
                ]),
        ];
    }
}
