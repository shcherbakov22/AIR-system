<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRuleDefinitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $scope = trim((string) $this->input('scope'));
        $description = trim((string) $this->input('description'));

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => $description === '' ? null : $description,
            'scope' => $scope === '' ? 'global' : $scope,
            'student_id' => $scope === 'student' && $this->filled('student_id')
                ? (int) $this->input('student_id')
                : null,
            'is_active' => $this->boolean('is_active', true),
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
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scope' => ['required', Rule::in(['global', 'student'])],
            'student_id' => [
                Rule::requiredIf(fn () => $this->input('scope') === 'student'),
                'nullable',
                'integer',
                Rule::exists('students', 'id'),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
