<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatMessage;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $students = Student::query()
            ->with(['user', 'latestAnnouncementMessage.sender'])
            ->orderBy('display_name')
            ->get();

        return Inertia::render('Admin/Announcements/Index', [
            'students' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
                'latest_message' => $student->latestAnnouncementMessage ? [
                    'body' => $student->latestAnnouncementMessage->body,
                    'created_at_label' => $student->latestAnnouncementMessage->created_at?->format('j M, H:i'),
                    'sender_name' => $student->latestAnnouncementMessage->sender?->name
                        ?? $student->latestAnnouncementMessage->sender?->username,
                    'has_attachment' => $student->latestAnnouncementMessage->hasAttachment(),
                ] : null,
            ]),
        ]);
    }

    public function show(Student $student): Response
    {
        return Inertia::render('Admin/Announcements/Show', [
            'studentThread' => $this->threadPayload($student),
        ]);
    }

    public function store(StoreChatMessageRequest $request, Student $student): RedirectResponse
    {
        $attachment = $request->file('attachment');
        $path = $attachment?->store("announcements/{$student->id}", 'local');

        ChatMessage::create([
            'student_id' => $student->id,
            'sender_user_id' => $request->user()->id,
            'channel' => 'announcement',
            'body' => $request->string('body')->trim()->toString() ?: null,
            'attachment_disk' => $path ? 'local' : null,
            'attachment_path' => $path,
            'attachment_name' => $attachment?->getClientOriginalName(),
            'attachment_mime' => $attachment?->getClientMimeType(),
            'attachment_size' => $attachment?->getSize(),
        ]);

        return redirect()
            ->route('admin.announcements.show', $student)
            ->with('success', 'Announcement sent.');
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
