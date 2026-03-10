<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PauseScheduleRunRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes'));

        $this->merge([
            'task_template_id' => ($taskTemplateId = trim((string) $this->input('task_template_id'))) === ''
                ? null
                : (int) $taskTemplateId,
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
            'task_template_id' => [
                'required',
                'integer',
                Rule::exists('task_templates', 'id'),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
