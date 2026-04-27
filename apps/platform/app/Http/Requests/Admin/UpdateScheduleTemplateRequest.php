<?php

namespace App\Http\Requests\Admin;

use App\Enums\ScheduleWeekday;
use App\Models\Student;
use App\Models\TaskTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes'));
        $entries = collect($this->input('entries', []))
            ->map(fn ($entry) => [
                'task_template_id' => ($taskTemplateId = trim((string) data_get($entry, 'task_template_id'))) === ''
                    ? null
                    : (int) $taskTemplateId,
                'notes' => ($entryNotes = trim((string) data_get($entry, 'notes'))) === ''
                    ? null
                    : $entryNotes,
            ])
            ->values()
            ->all();

        $this->merge([
            'notes' => $notes === '' ? null : $notes,
            'entries' => $entries,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists(Student::class, 'id'),
            ],
            'weekday' => ['required', Rule::enum(ScheduleWeekday::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.task_template_id' => [
                'required',
                'integer',
                Rule::exists(TaskTemplate::class, 'id'),
            ],
            'entries.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
