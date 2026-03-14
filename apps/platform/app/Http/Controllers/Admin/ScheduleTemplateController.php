<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScheduleWeekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScheduleTemplateRequest;
use App\Http\Requests\Admin\UpdateScheduleTemplateRequest;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleTemplateController extends Controller
{
    protected function studentOptions(): array
    {
        return Student::query()
            ->with('user')
            ->orderBy('display_name')
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user->username,
                'is_active' => $student->user->is_active,
            ])
            ->all();
    }

    protected function taskTemplateOptions(): array
    {
        return TaskTemplate::query()
            ->orderBy('title')
            ->get()
            ->map(fn (TaskTemplate $taskTemplate) => [
                'id' => $taskTemplate->id,
                'title' => $taskTemplate->title,
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

    protected function formatTime(string $time): string
    {
        return substr($time, 0, 5);
    }

    protected function formatEndTime(string $time, int $durationMinutes): string
    {
        return CarbonImmutable::createFromFormat('H:i', $this->formatTime($time))
            ->addMinutes($durationMinutes)
            ->format('H:i');
    }

    protected function resolveWeekday(?string $weekday): ?ScheduleWeekday
    {
        if ($weekday === null || $weekday === '') {
            return null;
        }

        return ScheduleWeekday::tryFrom($weekday);
    }

    protected function templateSortKey(ScheduleTemplate $scheduleTemplate): string
    {
        $firstStartTime = $scheduleTemplate->entries->first()?->start_time ?? '23:59:59';
        $weekday = $this->resolveWeekday($scheduleTemplate->weekday);

        return sprintf(
            '%02d-%s-%010d',
            $weekday?->sortOrder() ?? 99,
            $firstStartTime,
            $scheduleTemplate->id,
        );
    }

    protected function toPayload(ScheduleTemplate $scheduleTemplate): array
    {
        $scheduleTemplate->loadMissing(['student.user', 'entries.taskTemplate']);
        $weekday = $this->resolveWeekday($scheduleTemplate->weekday);

        return [
            'id' => $scheduleTemplate->id,
            'name' => $scheduleTemplate->name,
            'weekday' => [
                'value' => $weekday?->value ?? (string) $scheduleTemplate->weekday,
                'label' => $weekday?->label() ?? (string) $scheduleTemplate->weekday,
            ],
            'notes' => $scheduleTemplate->notes,
            'student' => [
                'id' => $scheduleTemplate->student->id,
                'display_name' => $scheduleTemplate->student->display_name,
                'username' => $scheduleTemplate->student->user->username,
            ],
            'entries' => $scheduleTemplate->entries
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'position' => $entry->position,
                    'task_template_id' => $entry->task_template_id,
                    'task_title' => $entry->resolvedTaskTitle(),
                    'task_instructions' => $entry->resolvedTaskInstructions(),
                    'start_time' => $this->formatTime((string) $entry->start_time),
                    'end_time' => $this->formatEndTime((string) $entry->start_time, $entry->duration_minutes),
                    'duration_minutes' => $entry->duration_minutes,
                    'notes' => $entry->notes,
                    'task_template' => [
                        'id' => $entry->taskTemplate->id,
                        'title' => $entry->taskTemplate->title,
                        'summary' => $entry->taskTemplate->summary,
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
            'student_id' => (string) $scheduleTemplate->student_id,
            'name' => $payload['name'],
            'weekday' => $payload['weekday']['value'],
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

    public function index(): Response
    {
        return Inertia::render('Admin/ScheduleTemplates/Index', [
            'scheduleTemplates' => ScheduleTemplate::query()
                ->with(['student.user', 'entries.taskTemplate'])
                ->get()
                ->sortBy(fn (ScheduleTemplate $scheduleTemplate) => $this->templateSortKey($scheduleTemplate))
                ->values()
                ->map(fn (ScheduleTemplate $scheduleTemplate) => $this->toPayload($scheduleTemplate))
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ScheduleTemplates/Create', [
            'students' => $this->studentOptions(),
            'taskTemplates' => $this->taskTemplateOptions(),
            'weekdays' => ScheduleWeekday::options(),
        ]);
    }

    public function store(StoreScheduleTemplateRequest $request): RedirectResponse
    {
        /** @var ScheduleTemplate $scheduleTemplate */
        $scheduleTemplate = DB::transaction(function () use ($request) {
            $entries = collect((array) $request->input('entries', []))->values();
            $taskTemplates = $this->selectedTaskTemplates($entries->all());

            $scheduleTemplate = ScheduleTemplate::create([
                'student_id' => (int) $request->input('student_id'),
                'name' => $request->string('name')->toString(),
                'weekday' => $request->enum('weekday', ScheduleWeekday::class)?->value,
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
            ->route('admin.schedule-templates.index')
            ->with('success', "Schedule {$scheduleTemplate->name} created.");
    }

    public function edit(ScheduleTemplate $scheduleTemplate): Response
    {
        $scheduleTemplate->loadMissing('entries.taskTemplate');

        return Inertia::render('Admin/ScheduleTemplates/Edit', [
            'scheduleTemplate' => $this->toFormPayload($scheduleTemplate),
            'students' => $this->studentOptions(),
            'taskTemplates' => $this->taskTemplateOptions(),
            'weekdays' => ScheduleWeekday::options(),
        ]);
    }

    public function update(UpdateScheduleTemplateRequest $request, ScheduleTemplate $scheduleTemplate): RedirectResponse
    {
        DB::transaction(function () use ($request, $scheduleTemplate) {
            $entries = collect((array) $request->input('entries', []))->values();
            $taskTemplates = $this->selectedTaskTemplates($entries->all());

            $scheduleTemplate->update([
                'student_id' => (int) $request->input('student_id'),
                'name' => $request->string('name')->toString(),
                'weekday' => $request->enum('weekday', ScheduleWeekday::class)?->value,
                'notes' => $request->input('notes'),
            ]);

            $scheduleTemplate->entries()->delete();
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
        });

        return redirect()
            ->route('admin.schedule-templates.index')
            ->with('success', "Schedule {$scheduleTemplate->name} updated.");
    }

    public function destroy(ScheduleTemplate $scheduleTemplate): RedirectResponse
    {
        $scheduleName = $scheduleTemplate->name;

        DB::transaction(function () use ($scheduleTemplate) {
            $scheduleTemplate->entries()->delete();
            $scheduleTemplate->delete();
        });

        return redirect()
            ->route('admin.schedule-templates.index')
            ->with('success', "Schedule {$scheduleName} deleted.");
    }
}
