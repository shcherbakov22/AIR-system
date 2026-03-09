<?php

namespace App\Http\Requests\Student;

use App\Enums\ScheduleWeekday;
use App\Models\ScheduleTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ManagesOwnScheduleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $notes = trim((string) $this->input('notes'));

        $entries = collect($this->input('entries', []))
            ->map(fn ($entry) => [
                'task_title' => trim((string) data_get($entry, 'task_title')),
                'task_summary' => ($taskSummary = trim((string) data_get($entry, 'task_summary'))) === ''
                    ? null
                    : $taskSummary,
                'task_instructions' => ($taskInstructions = trim((string) data_get($entry, 'task_instructions'))) === ''
                    ? null
                    : $taskInstructions,
                'start_time' => trim((string) data_get($entry, 'start_time')),
                'duration_minutes' => trim((string) data_get($entry, 'duration_minutes')),
                'notes' => ($entryNotes = trim((string) data_get($entry, 'notes'))) === ''
                    ? null
                    : $entryNotes,
            ])
            ->values()
            ->all();

        $this->merge([
            'name' => $name,
            'notes' => $notes === '' ? null : $notes,
            'is_active' => $this->boolean('is_active', true),
            'entries' => $entries,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function scheduleRules(array $currentTaskTemplateIds = [], ?int $ignoreScheduleTemplateId = null): array
    {
        $studentId = $this->user()?->student?->id;

        $weekdayRule = Rule::unique(ScheduleTemplate::class, 'weekday')->where(
            fn ($query) => $query->where('student_id', $studentId)
        );

        if ($ignoreScheduleTemplateId !== null) {
            $weekdayRule = $weekdayRule->ignore($ignoreScheduleTemplateId);
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'weekday' => ['required', Rule::enum(ScheduleWeekday::class), $weekdayRule],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'entries' => ['required', 'array', 'min:1', 'max:12'],
            'entries.*.task_title' => ['required', 'string', 'max:160'],
            'entries.*.task_summary' => ['nullable', 'string', 'max:2000'],
            'entries.*.task_instructions' => ['nullable', 'string', 'max:4000'],
            'entries.*.start_time' => ['required', 'date_format:H:i'],
            'entries.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'entries.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateEntrySequence($validator);
            },
        ];
    }

    protected function validateEntrySequence(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $previousStart = null;
        $previousEnd = null;

        foreach ((array) $this->input('entries', []) as $index => $entry) {
            $startTime = data_get($entry, 'start_time');
            $durationMinutes = (int) data_get($entry, 'duration_minutes');

            try {
                $start = CarbonImmutable::createFromFormat('H:i', (string) $startTime);
            } catch (\Throwable) {
                continue;
            }

            if ($previousStart !== null && $start->lessThan($previousStart)) {
                $validator->errors()->add(
                    "entries.{$index}.start_time",
                    'Блоки расписания должны быть расположены в хронологическом порядке.'
                );

                $previousStart = $start;
                $previousEnd = $start->addMinutes($durationMinutes);

                continue;
            }

            if ($previousEnd !== null && $start->lessThan($previousEnd)) {
                $validator->errors()->add(
                    "entries.{$index}.start_time",
                    'Блоки расписания не могут пересекаться.'
                );
            }

            $previousStart = $start;
            $previousEnd = $start->addMinutes($durationMinutes);
        }
    }
}
