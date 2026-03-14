<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Storage;
class ChatAttachmentController extends Controller
{
    public function show(ChatMessage $chatMessage)
    {
        $user = request()->user();

        abort_unless($user, 403);

        if ($user->isStudent()) {
            abort_unless(
                $chatMessage->isAnnouncement() || $user->student?->id === $chatMessage->student_id,
                404,
            );
        } else {
            abort_unless($user->isAdmin(), 403);
        }

        abort_unless($chatMessage->attachment_path && $chatMessage->attachment_disk, 404);

        $headers = $chatMessage->attachment_mime
            ? ['Content-Type' => $chatMessage->attachment_mime]
            : [];

        return Storage::disk($chatMessage->attachment_disk)->response(
            $chatMessage->attachment_path,
            $chatMessage->attachment_name ?? basename($chatMessage->attachment_path),
            $headers,
        );
    }
}
