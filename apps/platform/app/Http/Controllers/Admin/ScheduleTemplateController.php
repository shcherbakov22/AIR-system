<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScheduleWeekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScheduleTemplateRequest;
use App\Http\Requests\Admin\UpdateScheduleTemplateRequest;
use App\Models\ScheduleEntry;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskTemplate;
use Carbon\CarbonImmutable;
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

    protected function taskTemplateOptions(?ScheduleTemplate $scheduleTemplate = null): array
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
            $scheduleTemplate = ScheduleTemplate::create([
                'student_id' => (int) $request->input('student_id'),
                'name' => $request->string('name')->toString(),
                'weekday' => $request->enum('weekday', ScheduleWeekday::class)?->value,
                'notes' => $request->input('notes'),
                'created_by_user_id' => $request->user()->id,
            ]);

            $scheduleTemplate->entries()->create([
                'task_template_id' => (int) $request->input('task_template_id'),
                'position' => 1,
                'start_time' => $request->string('start_time')->toString(),
                'duration_minutes' => (int) $request->input('duration_minutes'),
                'notes' => $request->input('entry_notes'),
            ]);

            return $scheduleTemplate;
        });

        return redirect()
            ->route('admin.schedule-templates.index')
            ->with('success', "Schedule {$scheduleTemplate->name} created.");
    }

    public function edit(ScheduleTemplate $scheduleTemplate): Response
    {
        $scheduleTemplate->loadMissing('entries');
        $weekday = $this->resolveWeekday($scheduleTemplate->weekday);

        /** @var ScheduleEntry|null $entry */
        $entry = $scheduleTemplate->entries->sortBy('position')->first();

        return Inertia::render('Admin/ScheduleTemplates/Edit', [
            'scheduleTemplate' => [
                'id' => $scheduleTemplate->id,
                'student_id' => (string) $scheduleTemplate->student_id,
                'name' => $scheduleTemplate->name,
                'weekday' => $weekday?->value ?? (string) $scheduleTemplate->weekday,
                'notes' => $scheduleTemplate->notes ?? '',
                'entry' => [
                    'task_template_id' => (string) ($entry?->task_template_id ?? ''),
                    'start_time' => $entry ? $this->formatTime((string) $entry->start_time) : '09:00',
                    'duration_minutes' => $entry?->duration_minutes ?? 30,
                    'notes' => $entry?->notes ?? '',
                ],
            ],
            'students' => $this->studentOptions(),
            'taskTemplates' => $this->taskTemplateOptions($scheduleTemplate),
            'weekdays' => ScheduleWeekday::options(),
        ]);
    }

    public function update(UpdateScheduleTemplateRequest $request, ScheduleTemplate $scheduleTemplate): RedirectResponse
    {
        DB::transaction(function () use ($request, $scheduleTemplate) {
            $scheduleTemplate->update([
                'student_id' => (int) $request->input('student_id'),
                'name' => $request->string('name')->toString(),
                'weekday' => $request->enum('weekday', ScheduleWeekday::class)?->value,
                'notes' => $request->input('notes'),
            ]);

            $entry = $scheduleTemplate->entries()->orderBy('position')->first();

            if ($entry) {
                $entry->update([
                    'task_template_id' => (int) $request->input('task_template_id'),
                    'start_time' => $request->string('start_time')->toString(),
                    'duration_minutes' => (int) $request->input('duration_minutes'),
                    'notes' => $request->input('entry_notes'),
                ]);

                return;
            }

            $scheduleTemplate->entries()->create([
                'task_template_id' => (int) $request->input('task_template_id'),
                'position' => 1,
                'start_time' => $request->string('start_time')->toString(),
                'duration_minutes' => (int) $request->input('duration_minutes'),
                'notes' => $request->input('entry_notes'),
            ]);
        });

        return redirect()
            ->route('admin.schedule-templates.index')
            ->with('success', "Schedule {$scheduleTemplate->name} updated.");
    }
}
