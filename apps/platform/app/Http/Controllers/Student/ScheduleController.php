<?php

namespace App\Http\Controllers\Student;

use App\Enums\ScheduleWeekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreScheduleRequest;
use App\Http\Requests\Student\UpdateScheduleRequest;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $firstStartTime = $scheduleTemplate->entries->first()?->start_time ?? '23:59:59';

        return sprintf(
            '%02d-%s-%010d',
            $scheduleTemplate->weekday?->sortOrder() ?? 99,
            $firstStartTime,
            $scheduleTemplate->id,
        );
    }

    protected function toPayload(ScheduleTemplate $scheduleTemplate): array
    {
        $scheduleTemplate->loadMissing('entries.taskTemplate');

        return [
            'id' => $scheduleTemplate->id,
            'name' => $scheduleTemplate->name,
            'weekday' => [
                'value' => $scheduleTemplate->weekday->value,
                'label' => $scheduleTemplate->weekday->label(),
            ],
            'is_active' => $scheduleTemplate->is_active,
            'notes' => $scheduleTemplate->notes,
            'entries' => $scheduleTemplate->entries
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'position' => $entry->position,
                    'task_title' => $entry->resolvedTaskTitle(),
                    'task_summary' => $entry->resolvedTaskSummary(),
                    'task_instructions' => $entry->resolvedTaskInstructions(),
                    'start_time' => substr((string) $entry->start_time, 0, 5),
                    'duration_minutes' => $entry->duration_minutes,
                    'notes' => $entry->notes,
                    'task' => [
                        'title' => $entry->resolvedTaskTitle(),
                        'summary' => $entry->resolvedTaskSummary(),
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
            'weekday' => $payload['weekday']['value'],
            'is_active' => $payload['is_active'],
            'notes' => $payload['notes'] ?? '',
            'entries' => collect($payload['entries'])
                ->map(fn (array $entry) => [
                    'task_title' => (string) $entry['task_title'],
                    'task_summary' => (string) ($entry['task_summary'] ?? ''),
                    'task_instructions' => (string) ($entry['task_instructions'] ?? ''),
                    'start_time' => $entry['start_time'],
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
            'weekdays' => ScheduleWeekday::options(),
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
            $scheduleTemplate = ScheduleTemplate::create([
                'student_id' => $student->id,
                'name' => $request->string('name')->toString(),
                'weekday' => $request->enum('weekday', ScheduleWeekday::class)?->value,
                'is_active' => $request->boolean('is_active'),
                'notes' => $request->input('notes'),
                'created_by_user_id' => $request->user()->id,
            ]);

            foreach ((array) $request->input('entries', []) as $index => $entry) {
                $scheduleTemplate->entries()->create([
                    'task_template_id' => null,
                    'task_title' => (string) data_get($entry, 'task_title'),
                    'task_summary' => data_get($entry, 'task_summary'),
                    'task_instructions' => data_get($entry, 'task_instructions'),
                    'position' => $index + 1,
                    'start_time' => (string) data_get($entry, 'start_time'),
                    'duration_minutes' => (int) data_get($entry, 'duration_minutes'),
                    'notes' => data_get($entry, 'notes'),
                ]);
            }

            return $scheduleTemplate;
        });

        return redirect()
            ->route('student.schedules.index')
            ->with('success', "Расписание {$scheduleTemplate->name} сохранено.");
    }

    public function edit(Request $request, ScheduleTemplate $scheduleTemplate): Response
    {
        $student = $this->currentStudent($request);

        if (! $student->canManageOwnSchedule()) {
            abort(403);
        }

        $scheduleTemplate = $this->loadOwnedSchedule($student, $scheduleTemplate);

        return Inertia::render('Student/Schedules/Edit', [
            'scheduleTemplate' => $this->toFormPayload($scheduleTemplate),
            'weekdays' => ScheduleWeekday::options(),
        ]);
    }

    public function update(UpdateScheduleRequest $request, ScheduleTemplate $scheduleTemplate): RedirectResponse
    {
        $student = $this->currentStudent($request);

        if (! $student->canManageOwnSchedule()) {
            abort(403);
        }

        $scheduleTemplate = $this->loadOwnedSchedule($student, $scheduleTemplate);

        DB::transaction(function () use ($request, $scheduleTemplate) {
            $scheduleTemplate->update([
                'name' => $request->string('name')->toString(),
                'weekday' => $request->enum('weekday', ScheduleWeekday::class)?->value,
                'is_active' => $request->boolean('is_active'),
                'notes' => $request->input('notes'),
            ]);

            $scheduleTemplate->entries()->delete();

            foreach ((array) $request->input('entries', []) as $index => $entry) {
                $scheduleTemplate->entries()->create([
                    'task_template_id' => null,
                    'task_title' => (string) data_get($entry, 'task_title'),
                    'task_summary' => data_get($entry, 'task_summary'),
                    'task_instructions' => data_get($entry, 'task_instructions'),
                    'position' => $index + 1,
                    'start_time' => (string) data_get($entry, 'start_time'),
                    'duration_minutes' => (int) data_get($entry, 'duration_minutes'),
                    'notes' => data_get($entry, 'notes'),
                ]);
            }
        });

        return redirect()
            ->route('student.schedules.index')
            ->with('success', "Расписание {$scheduleTemplate->name} обновлено.");
    }
}
