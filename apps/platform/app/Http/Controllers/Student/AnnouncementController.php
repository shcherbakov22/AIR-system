<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Services\StudentCommunicationGateService;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function show(StudentCommunicationGateService $communicationGateService): Response
    {
        $student = request()->user()?->student;

        abort_unless($student, 403);

        $communicationGateService->markAnnouncementsSeen($student);
        $student->refresh();

        return Inertia::render('Student/Announcements/Show', [
            'announcementThread' => $this->threadPayload(),
        ]);
    }

    private function threadPayload(): array
    {
        $messages = ChatMessage::query()
            ->with('sender')
            ->where('channel', 'announcement')
            ->orderBy('created_at')
            ->get();
        
        return [
            'messages' => $messages
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
