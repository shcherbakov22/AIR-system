<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\Student;

class StudentCommunicationGateService
{
    public function latestUnreadMentorChat(Student $student): ?ChatMessage
    {
        return ChatMessage::query()
            ->with('sender')
            ->where('student_id', $student->id)
            ->where('channel', 'chat')
            ->whereHas('sender', fn ($query) => $query->where('role', UserRole::Admin->value))
            ->when(
                $student->last_seen_mentor_chat_at,
                fn ($query) => $query->where('created_at', '>', $student->last_seen_mentor_chat_at),
            )
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    public function latestUnreadAnnouncement(Student $student): ?ChatMessage
    {
        return ChatMessage::query()
            ->with('sender')
            ->where('channel', 'announcement')
            ->whereHas('sender', fn ($query) => $query->where('role', UserRole::Admin->value))
            ->when(
                $student->last_seen_announcements_at,
                fn ($query) => $query->where('created_at', '>', $student->last_seen_announcements_at),
            )
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    public function payload(Student $student): array
    {
        $unreadMentorChat = $this->latestUnreadMentorChat($student);
        $unreadAnnouncement = $this->latestUnreadAnnouncement($student);

        return [
            'has_unread' => $unreadMentorChat !== null || $unreadAnnouncement !== null,
            'unread_mentor_chat' => $unreadMentorChat ? [
                'id' => $unreadMentorChat->id,
                'body' => $unreadMentorChat->body,
                'created_at_label' => $unreadMentorChat->created_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                'sender_name' => $unreadMentorChat->sender?->name ?: 'Mentor',
                'has_attachment' => $unreadMentorChat->hasAttachment(),
            ] : null,
            'unread_announcement' => $unreadAnnouncement ? [
                'id' => $unreadAnnouncement->id,
                'body' => $unreadAnnouncement->body,
                'created_at_label' => $unreadAnnouncement->created_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                'sender_name' => $unreadAnnouncement->sender?->name ?: 'Mentor',
                'has_attachment' => $unreadAnnouncement->hasAttachment(),
            ] : null,
        ];
    }

    public function blockingMessage(Student $student): ?string
    {
        $payload = $this->payload($student);

        if (! $payload['has_unread']) {
            return null;
        }

        $parts = [];

        if ($payload['unread_mentor_chat']) {
            $chat = $payload['unread_mentor_chat'];
            $parts[] = 'an unread mentor message'
                . ($chat['created_at_label'] ? " ({$chat['created_at_label']})" : '');
        }

        if ($payload['unread_announcement']) {
            $announcement = $payload['unread_announcement'];
            $parts[] = 'an unread announcement'
                . ($announcement['created_at_label'] ? " ({$announcement['created_at_label']})" : '');
        }

        return 'Read '.implode(' and ', $parts).' before continuing the schedule.';
    }

    public function markMentorChatSeen(Student $student): void
    {
        $latestMentorChatAt = ChatMessage::query()
            ->where('student_id', $student->id)
            ->where('channel', 'chat')
            ->whereHas('sender', fn ($query) => $query->where('role', UserRole::Admin->value))
            ->max('created_at');

        $student->forceFill([
            'last_seen_mentor_chat_at' => $latestMentorChatAt,
        ])->save();
    }

    public function markAnnouncementsSeen(Student $student): void
    {
        $latestAnnouncementAt = ChatMessage::query()
            ->where('channel', 'announcement')
            ->whereHas('sender', fn ($query) => $query->where('role', UserRole::Admin->value))
            ->max('created_at');

        $student->forceFill([
            'last_seen_announcements_at' => $latestAnnouncementAt,
        ])->save();
    }
}
