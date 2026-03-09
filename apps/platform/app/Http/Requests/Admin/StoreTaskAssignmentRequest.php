<?php

namespace App\Http\Requests\Admin;

use App\Models\Student;
use App\Models\TaskTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskAssignmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes'));

        $this->merge([
            'notes' => $notes === '' ? null : $notes,
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
            'task_template_id' => [
                'required',
                'integer',
                Rule::exists(TaskTemplate::class, 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'status' => ['required', 'string', Rule::in(['assigned', 'paused', 'completed'])],
            'due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
