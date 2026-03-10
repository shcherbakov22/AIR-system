<?php

namespace App\Http\Requests\Admin;

use App\Enums\ScheduleWeekday;
use App\Models\Student;
use App\Models\TaskTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $notes = trim((string) $this->input('notes'));
        $entryNotes = trim((string) $this->input('entry_notes'));

        $this->merge([
            'name' => $name,
            'notes' => $notes === '' ? null : $notes,
            'entry_notes' => $entryNotes === '' ? null : $entryNotes,
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
            'name' => ['required', 'string', 'max:120'],
            'weekday' => ['required', Rule::enum(ScheduleWeekday::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'task_template_id' => [
                'required',
                'integer',
                Rule::exists(TaskTemplate::class, 'id'),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'entry_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
