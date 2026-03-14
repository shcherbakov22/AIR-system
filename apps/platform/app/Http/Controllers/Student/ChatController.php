<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatMessage;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function show(): Response
    {
        $student = request()->user()?->student;

        abort_unless($student, 403);

        return Inertia::render('Student/Chat/Show', [
            'studentThread' => $this->threadPayload($student),
        ]);
    }

    public function store(StoreChatMessageRequest $request): RedirectResponse
    {
        $student = $request->user()?->student;

        abort_unless($student, 403);

        $attachment = $request->file('attachment');
        $path = $attachment?->store("chat/{$student->id}", 'local');

        ChatMessage::create([
            'student_id' => $student->id,
            'sender_user_id' => $request->user()->id,
            'body' => $request->string('body')->trim()->toString() ?: null,
            'attachment_disk' => $path ? 'local' : null,
            'attachment_path' => $path,
            'attachment_name' => $attachment?->getClientOriginalName(),
            'attachment_mime' => $attachment?->getClientMimeType(),
            'attachment_size' => $attachment?->getSize(),
        ]);

        return redirect()
            ->route('student.chat.show')
            ->with('success', 'Message sent.');
    }

    private function threadPayload(Student $student): array
    {
        $student->loadMissing(['user', 'chatMessages.sender']);

        return [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
            ],
            'messages' => $student->chatMessages
                ->sortBy('created_at')
                ->values()
                ->map(fn (ChatMessage $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'created_at_label' => $message->created_at?->format('j M, H:i'),
                    'sent_by_role' => $message->sender?->isAdmin() ? 'mentor' : 'student',
                    'sent_by_name' => $message->sender?->isAdmin()
                        ? ($message->sender?->name ?: 'Mentor')
                        : $student->display_name,
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
