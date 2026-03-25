<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\Student;

class StudentCommunicationGateService
{
    public function latestUnreadStudentChat(Student $student): ?ChatMessage
    {
        return ChatMessage::query()
            ->with('sender')
            ->where('student_id', $student->id)
            ->where('channel', 'chat')
            ->whereHas('sender', fn ($query) => $query->where('role', UserRole::Student->value))
            ->when(
                $student->last_seen_student_chat_at,
                fn ($query) => $query->where('created_at', '>', $student->last_seen_student_chat_at),
            )
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

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
        $unreadStudentChat = $this->latestUnreadStudentChat($student);
        $unreadMentorChat = $this->latestUnreadMentorChat($student);
        $unreadAnnouncement = $this->latestUnreadAnnouncement($student);

        return [
            'has_unread' => $unreadMentorChat !== null || $unreadAnnouncement !== null,
            'has_unread_student_chat' => $unreadStudentChat !== null,
            'unread_student_chat' => $unreadStudentChat ? [
                'id' => $unreadStudentChat->id,
                'body' => $unreadStudentChat->body,
                'created_at_label' => $unreadStudentChat->created_at?->locale(app()->getLocale())->translatedFormat('d M Y, H:i'),
                'sender_name' => $unreadStudentChat->sender?->name ?: $student->display_name,
                'has_attachment' => $unreadStudentChat->hasAttachment(),
            ] : null,
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

        if ($payload['unread_mentor_chat'] && $payload['unread_announcement']) {
            $chat = $payload['unread_mentor_chat'];
            $announcement = $payload['unread_announcement'];

            return 'Read the unread mentor chat'
                . ($chat['created_at_label'] ? " ({$chat['created_at_label']})" : '')
                . ' and the unread announcement'
                . ($announcement['created_at_label'] ? " ({$announcement['created_at_label']})" : '')
                . ' before continuing the schedule.';
        }

        if ($payload['unread_mentor_chat']) {
            $chat = $payload['unread_mentor_chat'];

            return 'Read the unread mentor chat'
                . ($chat['created_at_label'] ? " ({$chat['created_at_label']})" : '')
                . ' before continuing the schedule.';
        }

        $announcement = $payload['unread_announcement'];

        return 'Read the unread announcement'
            . ($announcement && $announcement['created_at_label'] ? " ({$announcement['created_at_label']})" : '')
            . ' before continuing the schedule.';
    }

    public function adminBlockingMessage(Student $student): ?string
    {
        $chat = $this->payload($student)['unread_student_chat'];

        if (! $chat) {
            return null;
        }

        return 'Read the unread student chat'
            . ($chat['created_at_label'] ? " ({$chat['created_at_label']})" : '')
            . ' before using other dashboard actions.';
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

    public function markStudentChatSeen(Student $student): void
    {
        $latestStudentChatAt = ChatMessage::query()
            ->where('student_id', $student->id)
            ->where('channel', 'chat')
            ->whereHas('sender', fn ($query) => $query->where('role', UserRole::Student->value))
            ->max('created_at');

        $student->forceFill([
            'last_seen_student_chat_at' => $latestStudentChatAt,
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
