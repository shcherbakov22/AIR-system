<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class PauseScheduleRunRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $taskTitle = trim((string) $this->input('task_title'));
        $notes = trim((string) $this->input('notes'));

        $this->merge([
            'task_title' => $taskTitle,
            'notes' => $notes === '' ? null : $notes,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    public function rules(): array
    {
        return [
            'task_title' => ['required', 'string', 'max:160'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
