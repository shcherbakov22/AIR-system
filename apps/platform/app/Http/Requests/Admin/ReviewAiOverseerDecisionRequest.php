<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewAiOverseerDecisionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes'));

        $this->merge([
            'status' => trim((string) $this->input('status')),
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
            'status' => ['required', Rule::in(['approved', 'denied', 'mentor_review'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
