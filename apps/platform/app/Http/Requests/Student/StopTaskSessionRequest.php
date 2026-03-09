<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StopTaskSessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $completionNotes = trim((string) $this->input('completion_notes'));

        $this->merge([
            'completion_notes' => $completionNotes === '' ? null : $completionNotes,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'completion_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
