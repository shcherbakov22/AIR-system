<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $instructions = trim((string) $this->input('instructions'));

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'instructions' => $instructions === '' ? null : $instructions,
            'requires_internet' => $this->boolean('requires_internet'),
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
            'instructions' => ['nullable', 'string', 'max:5000'],
            'default_duration_minutes' => ['required', 'integer', 'min:1', 'max:10000'],
            'requires_internet' => ['required', 'boolean'],
        ];
    }
}
