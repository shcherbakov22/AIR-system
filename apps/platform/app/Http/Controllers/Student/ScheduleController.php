<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreScheduleRequest;
use App\Http\Requests\Student\UpdateScheduleRequest;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    protected function currentStudent(Request $request): Student
    {
        $student = $request->user()?->student?->loadMissing('setting');

        if (! $student) {
            abort(403);
        }

        return $student;
    }

    protected function loadOwnedSchedule(Student $student, ScheduleTemplate $scheduleTemplate): ScheduleTemplate
    {
        return ScheduleTemplate::query()
            ->whereKey($scheduleTemplate->id)
            ->where('student_id', $student->id)
            ->with(['entries.taskTemplate'])
            ->firstOrFail();
    }

    protected function scheduleSortKey(ScheduleTemplate $scheduleTemplate): string
    {
        return sprintf(
            '%s-%010d',
            mb_strtolower($scheduleTemplate->name),
            $scheduleTemplate->id,
        );
    }

    protected function generatedStartTimeForIndex(int $position, int $durationMinutes): string
    {
        $baseMinutes = 9 * 60;
        $totalMinutes = min(23 * 60 + 55, $baseMinutes + max(0, $position - 1) * max(1, $durationMinutes));
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    protected function taskTemplateOptions(array $includeIds = []): array
    {
        return TaskTemplate::query()
            ->orderBy('title')
            ->get()
            ->map(fn (TaskTemplate $taskTemplate) => [
                'id' => $taskTemplate->id,
                'title' => $taskTemplate->title,
                'instructions' => $taskTemplate->instructions,
                'default_duration_minutes' => $taskTemplate->default_duration_minutes,
            ])
            ->all();
    }

    protected function selectedTaskTemplates(array $entries): Collection
    {
        return TaskTemplate::query()
            ->whereIn(
                'id',
                collect($entries)
                    ->pluck('task_template_id')
                    ->filter(fn ($taskTemplateId) => $taskTemplateId !== null)
                    ->map(fn ($taskTemplateId) => (int) $taskTemplateId)
                    ->unique()
                    ->values()
                    ->all(),
            )
            ->get()
            ->keyBy('id');
    }

    protected function toPayload(ScheduleTemplate $scheduleTemplate): array
    {
        $scheduleTemplate->loadMissing('entries.taskTemplate');

        return [
            'id' => $scheduleTemplate->id,
            'name' => $scheduleTemplate->name,
            'notes' => $scheduleTemplate->notes,
            'entries' => $scheduleTemplate->entries
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'position' => $entry->position,
                    'task_template_id' => $entry->task_template_id,
                    'task_title' => $entry->resolvedTaskTitle(),
                    'task_instructions' => $entry->resolvedTaskInstructions(),
                    'start_time' => substr((string) $entry->start_time, 0, 5),
                    'duration_minutes' => $entry->duration_minutes,
                    'notes' => $entry->notes,
                    'task' => [
                        'title' => $entry->resolvedTaskTitle(),
                        'instructions' => $entry->resolvedTaskInstructions(),
                    ],
                ])
                ->values()
                ->all(),
        ];
    }

    protected function toFormPayload(ScheduleTemplate $scheduleTemplate): array
    {
        $payload = $this->toPayload($scheduleTemplate);

        return [
            'id' => $payload['id'],
            'name' => $payload['name'],
            'notes' => $payload['notes'] ?? '',
            'entries' => collect($payload['entries'])
                ->map(fn (array $entry) => [
                    'task_template_id' => $entry['task_template_id'],
                    'task_title' => (string) $entry['task_title'],
                    'task_instructions' => (string) ($entry['task_instructions'] ?? ''),
                    'duration_minutes' => $entry['duration_minutes'],
                    'notes' => $entry['notes'] ?? '',
                ])
                ->all(),
        ];
    }

    public function index(Request $request): Response
    {
        $student = $this->currentStudent($request);

        if (! $student->canManageOwnSchedule()) {
            abort(403);
        }

        return Inertia::render('Student/Schedules/Index', [
            'scheduleTemplates' => ScheduleTemplate::query()
                ->where('student_id', $student->id)
                ->with(['entries.taskTemplate'])
                ->get()
                ->sortBy(fn (ScheduleTemplate $scheduleTemplate) => $this->scheduleSortKey($scheduleTemplate))
                ->values()
                ->map(fn (ScheduleTemplate $scheduleTemplate) => $this->toPayload($scheduleTemplate))
                ->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $student = $this->currentStudent($request);

        if (! $student->canManageOwnSchedule()) {
            abort(403);
        }

        return Inertia::render('Student/Schedules/Create', [
            'taskTemplates' => $this->taskTemplateOptions(),
        ]);
    }

    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $student = $this->currentStudent($request);

        if (! $student->canManageOwnSchedule()) {
            abort(403);
        }

        /** @var ScheduleTemplate $scheduleTemplate */
        $scheduleTemplate = DB::transaction(function () use ($request, $student) {
            $entries = collect((array) $request->input('entries', []))->values();
            $taskTemplates = $this->selectedTaskTemplates($entries->all());

            $scheduleTemplate = ScheduleTemplate::create([
                'student_id' => $student->id,
                'name' => $request->string('name')->toString(),
                'weekday' => 'monday',
                'notes' => $request->input('notes'),
                'created_by_user_id' => $request->user()->id,
            ]);

            $currentMinutes = 9 * 60;

            foreach ($entries as $index => $entry) {
                $taskTemplate = $taskTemplates->get((int) data_get($entry, 'task_template_id'));

                if (! $taskTemplate) {
                    continue;
                }

                $scheduleTemplate->entries()->create([
                    'task_template_id' => $taskTemplate->id,
                    'task_title' => null,
                    'task_summary' => null,
                    'task_instructions' => null,
                    'position' => $index + 1,
                    'start_time' => sprintf('%02d:%02d', intdiv($currentMinutes, 60), $currentMinutes % 60),
                    'duration_minutes' => $taskTemplate->default_duration_minutes,
                    'notes' => data_get($entry, 'notes'),
                ]);

                $currentMinutes = min(23 * 60 + 55, $currentMinutes + $taskTemplate->default_duration_minutes);
            }

            return $scheduleTemplate;
        });

        return redirect()
            ->route('student.schedules.index')
            ->with('success', "Schedule {$scheduleTemplate->name} saved.");
    }

    public function edit(Request $request, ScheduleTemplate $scheduleTemplate): Response
    {
        $student = $this->currentStudent($request);

        if (! $student->canManageOwnSchedule()) {
            abort(403);
        }

        $this->loadOwnedSchedule($student, $scheduleTemplate);

        abort(403);
    }

    public function update(UpdateScheduleRequest $request, ScheduleTemplate $scheduleTemplate): RedirectResponse
    {
        $student = $this->currentStudent($request);

        if (! $student->canManageOwnSchedule()) {
            abort(403);
        }

        $this->loadOwnedSchedule($student, $scheduleTemplate);

        abort(403);
    }

    public function destroy(Request $request, ScheduleTemplate $scheduleTemplate): RedirectResponse
    {
        $student = $this->currentStudent($request);

        if (! $student->canManageOwnSchedule()) {
            abort(403);
        }

        $this->loadOwnedSchedule($student, $scheduleTemplate);

        abort(403);
    }
}
