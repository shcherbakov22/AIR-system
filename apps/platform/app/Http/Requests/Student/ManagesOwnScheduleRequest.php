<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ManagesOwnScheduleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $notes = trim((string) $this->input('notes'));

        $entries = collect($this->input('entries', []))
            ->map(fn ($entry) => [
                'task_template_id' => ($taskTemplateId = trim((string) data_get($entry, 'task_template_id'))) === ''
                    ? null
                    : (int) $taskTemplateId,
                'notes' => ($entryNotes = trim((string) data_get($entry, 'notes'))) === ''
                    ? null
                    : $entryNotes,
            ])
            ->values()
            ->all();

        $this->merge([
            'name' => $name,
            'notes' => $notes === '' ? null : $notes,
            'entries' => $entries,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function scheduleRules(array $currentTaskTemplateIds = [], ?int $ignoreScheduleTemplateId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'entries' => ['required', 'array', 'min:1', 'max:12'],
            'entries.*.task_template_id' => [
                'required',
                'integer',
                Rule::exists('task_templates', 'id'),
            ],
            'entries.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
            },
        ];
    }
}
