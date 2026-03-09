<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $summary = trim((string) $this->input('summary'));
        $instructions = trim((string) $this->input('instructions'));

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'summary' => $summary === '' ? null : $summary,
            'instructions' => $instructions === '' ? null : $instructions,
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
            'summary' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'default_duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
