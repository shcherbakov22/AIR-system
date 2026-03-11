<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PauseScheduleRunRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'task_template_id' => ($taskTemplateId = trim((string) $this->input('task_template_id'))) === ''
                ? null
                : (int) $taskTemplateId,
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
        ];
    }
}
