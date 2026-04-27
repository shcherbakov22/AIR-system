<?php

namespace App\Http\Controllers;

use App\Models\StudentAssignment;
use Illuminate\Support\Facades\Storage;

class AssignmentAttachmentController extends Controller
{
    public function show(StudentAssignment $studentAssignment)
    {
        $user = request()->user();

        abort_unless($user, 403);

        if ($user->isStudent()) {
            abort_unless($user->student?->id === $studentAssignment->student_id, 404);
        } else {
            abort_unless($user->isAdmin(), 403);
        }

        abort_unless($studentAssignment->attachment_path && $studentAssignment->attachment_disk, 404);

        $headers = $studentAssignment->attachment_mime
            ? ['Content-Type' => $studentAssignment->attachment_mime]
            : [];

        return Storage::disk($studentAssignment->attachment_disk)->response(
            $studentAssignment->attachment_path,
            $studentAssignment->attachment_name ?? basename($studentAssignment->attachment_path),
            $headers,
        );
    }
}
