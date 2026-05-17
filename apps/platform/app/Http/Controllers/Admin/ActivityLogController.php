<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'student_id' => $request->integer('student_id') ?: null,
            'category' => $request->string('category')->toString() ?: null,
            'action' => $request->string('action')->toString() ?: null,
            'search' => $request->string('search')->toString() ?: null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
        ];

        $logs = ActivityLog::query()
            ->with(['student.user', 'actor'])
            ->when($filters['student_id'], fn ($query, $studentId) => $query->where('student_id', $studentId))
            ->when($filters['category'], fn ($query, $category) => $query->where('category', $category))
            ->when($filters['action'], fn ($query, $action) => $query->where('action', $action))
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', '%'.$search.'%')
                        ->orWhere('action', 'like', '%'.$search.'%')
                        ->orWhere('category', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['date_from'], fn ($query, $date) => $query->where('occurred_at', '>=', $date.' 00:00:00'))
            ->when($filters['date_to'], fn ($query, $date) => $query->where('occurred_at', '<=', $date.' 23:59:59'))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(80)
            ->withQueryString();

        return Inertia::render('Admin/Logs/Index', [
            'filters' => $filters,
            'students' => Student::query()
                ->with('user')
                ->orderBy('display_name')
                ->get()
                ->map(fn (Student $student) => [
                    'id' => $student->id,
                    'display_name' => $student->display_name,
                    'username' => $student->user->username,
                ]),
            'categories' => ActivityLog::query()
                ->select('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'actions' => ActivityLog::query()
                ->select('action')
                ->when($filters['category'], fn ($query, $category) => $query->where('category', $category))
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'logs' => [
                'data' => $logs->getCollection()->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'occurred_at_label' => $log->occurred_at?->format('j M, H:i:s'),
                    'category' => $log->category,
                    'action' => $log->action,
                    'description' => $log->description,
                    'student' => $log->student ? [
                        'id' => $log->student->id,
                        'display_name' => $log->student->display_name,
                        'username' => $log->student->user->username,
                    ] : null,
                    'actor' => $log->actor ? [
                        'id' => $log->actor->id,
                        'name' => $log->actor->name,
                        'username' => $log->actor->username,
                    ] : null,
                    'subject' => [
                        'type' => class_basename((string) $log->subject_type),
                        'id' => $log->subject_id,
                    ],
                    'metadata' => $log->metadata ?? [],
                ]),
                'links' => $logs->linkCollection(),
            ],
        ]);
    }

    public function destroyCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
        ]);

        $deleted = ActivityLog::query()
            ->where('category', $validated['category'])
            ->delete();

        return back()->with('success', "Cleared {$deleted} ".str_replace('_', ' ', $validated['category']).' log'.($deleted === 1 ? '' : 's').'.');
    }
}
