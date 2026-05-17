<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiOverseerDecisionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $reason = trim((string) $this->input('student_reason'));

        $this->merge([
            'request_type' => trim((string) $this->input('request_type')),
            'intent' => trim((string) $this->input('intent', 'decide')),
            'student_reason' => $reason === '' ? null : $reason,
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
            'request_type' => ['required', Rule::in(['skip_task', 'remove_violation'])],
            'intent' => ['required', Rule::in(['chat', 'decide'])],
            'student_reason' => ['required', 'string', 'max:2000'],
            'ai_overseer_decision_id' => ['nullable', 'integer', 'exists:ai_overseer_decisions,id'],
            'schedule_run_block_id' => ['nullable', 'integer', 'exists:schedule_run_blocks,id'],
            'violation_id' => ['nullable', 'integer', 'exists:violations,id'],
        ];
    }
}
