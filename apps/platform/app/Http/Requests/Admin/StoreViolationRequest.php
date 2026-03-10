<?php

namespace App\Http\Requests\Admin;

use App\Models\RuleDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreViolationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes'));

        $this->merge([
            'notes' => $notes === '' ? null : $notes,
            'status' => 'open',
            'student_id' => $this->filled('student_id') ? (int) $this->input('student_id') : null,
            'rule_definition_id' => $this->filled('rule_definition_id') ? (int) $this->input('rule_definition_id') : null,
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
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'rule_definition_id' => [
                'required',
                'integer',
                Rule::exists('rule_definitions', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'occurred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ruleDefinition = RuleDefinition::query()->find($this->input('rule_definition_id'));

                if (! $ruleDefinition) {
                    return;
                }

                if ($ruleDefinition->scope === 'student' && $ruleDefinition->student_id !== (int) $this->input('student_id')) {
                    $validator->errors()->add('rule_definition_id', 'Выбранное правило не применяется к этому ученику.');
                }
            },
        ];
    }
}
