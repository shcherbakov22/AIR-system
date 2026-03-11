<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRuleDefinitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $description = trim((string) $this->input('description'));

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => $description === '' ? null : $description,
            'scope' => 'global',
            'student_id' => null,
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
            'is_active' => ['required', 'boolean'],
        ];
    }
}
