<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatMessage;
use App\Models\Student;
use App\Services\StudentCommunicationGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(): Response
    {
        $students = Student::query()
            ->with(['user', 'latestChatMessage.sender'])
            ->orderBy('display_name')
            ->get();

        return Inertia::render('Admin/Chats/Index', [
            'students' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
                'latest_message' => $student->latestChatMessage ? [
                    'body' => $student->latestChatMessage->body,
                    'created_at_label' => $student->latestChatMessage->created_at?->format('j M, H:i'),
                    'sender_name' => $student->latestChatMessage->sender?->name
                        ?? $student->latestChatMessage->sender?->username,
                    'has_attachment' => $student->latestChatMessage->hasAttachment(),
                ] : null,
            ]),
        ]);
    }

    public function show(Student $student, StudentCommunicationGateService $communicationGateService): Response
    {
        $communicationGateService->markStudentChatSeen($student);
        $student->refresh();

        return Inertia::render('Admin/Chats/Show', [
            'studentThread' => $this->threadPayload($student),
        ]);
    }

    public function markRead(Student $student, StudentCommunicationGateService $communicationGateService): RedirectResponse
    {
        $communicationGateService->markStudentChatSeen($student);

        return back();
    }

    public function markMessageRead(Student $student, ChatMessage $chatMessage, StudentCommunicationGateService $communicationGateService): RedirectResponse
    {
        abort_unless($chatMessage->student_id === $student->id && $chatMessage->channel === 'chat', 404);

        $communicationGateService->markStudentChatMessageSeen($student, $chatMessage);

        return back();
    }

    public function store(StoreChatMessageRequest $request, Student $student): RedirectResponse
    {
        $attachment = $request->file('attachment');

        $path = $attachment?->store("chat/{$student->id}", 'local');

        ChatMessage::create([
            'student_id' => $student->id,
            'sender_user_id' => $request->user()->id,
            'channel' => 'chat',
            'body' => $request->string('body')->trim()->toString() ?: null,
            'attachment_disk' => $path ? 'local' : null,
            'attachment_path' => $path,
            'attachment_name' => $attachment?->getClientOriginalName(),
            'attachment_mime' => $attachment?->getClientMimeType(),
            'attachment_size' => $attachment?->getSize(),
        ]);

        return redirect()
            ->route('admin.chats.show', $student)
            ->with('success', 'Message sent.');
    }

    public function destroy(Student $student, ChatMessage $chatMessage): RedirectResponse
    {
        abort_unless($chatMessage->student_id === $student->id && $chatMessage->channel === 'chat', 404);

        if ($chatMessage->hasAttachment()) {
            Storage::disk($chatMessage->attachment_disk ?? 'local')->delete($chatMessage->attachment_path);
        }

        $chatMessage->delete();

        return redirect()
            ->route('admin.chats.show', $student)
            ->with('success', 'Message deleted.');
    }

    private function threadPayload(Student $student): array
    {
        $student->loadMissing('user');
        $messages = ChatMessage::query()
            ->with('sender')
            ->where('student_id', $student->id)
            ->where('channel', 'chat')
            ->orderBy('created_at')
            ->get();

        return [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
            ],
            'messages' => $messages
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
                    'delete_url' => route('admin.chats.destroy', [$student, $message]),
                ]),
        ];
    }
}
