<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaskSession;
use App\Services\TaskSessionUnfinishService;
use Illuminate\Http\RedirectResponse;

class TaskSessionController extends Controller
{
    public function unfinished(TaskSession $taskSession, TaskSessionUnfinishService $taskSessionUnfinishService): RedirectResponse
    {
        $result = $taskSessionUnfinishService->markUnfinished($taskSession, auth()->id());

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
