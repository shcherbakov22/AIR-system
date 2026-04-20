<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatMessage;
use App\Services\StudentDeviceMessageDisplayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Announcements/Index', [
            'announcementThread' => $this->threadPayload(),
        ]);
    }

    public function store(
        StoreChatMessageRequest $request,
        StudentDeviceMessageDisplayService $studentDeviceMessageDisplayService,
    ): RedirectResponse
    {
        $attachment = $request->file('attachment');
        $path = $attachment?->store('announcements/global', 'local');

        $message = ChatMessage::create([
            'student_id' => null,
            'sender_user_id' => $request->user()->id,
            'channel' => 'announcement',
            'body' => $request->string('body')->trim()->toString() ?: null,
            'attachment_disk' => $path ? 'local' : null,
            'attachment_path' => $path,
            'attachment_name' => $attachment?->getClientOriginalName(),
            'attachment_mime' => $attachment?->getClientMimeType(),
            'attachment_size' => $attachment?->getSize(),
        ]);

        $studentDeviceMessageDisplayService->queueAnnouncement($message, $request->user());

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement sent.');
    }

    public function destroy(ChatMessage $chatMessage): RedirectResponse
    {
        abort_unless($chatMessage->channel === 'announcement', 404);

        if ($chatMessage->hasAttachment()) {
            Storage::disk($chatMessage->attachment_disk ?? 'local')->delete($chatMessage->attachment_path);
        }

        $chatMessage->delete();

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement deleted.');
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
                    'delete_url' => route('admin.announcements.destroy', $message),
                ]),
        ];
    }
}
