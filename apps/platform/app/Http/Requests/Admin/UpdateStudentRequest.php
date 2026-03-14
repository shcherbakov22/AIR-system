<?php

namespace App\Http\Requests\Admin;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $preferredTimezone = trim((string) $this->input('preferred_timezone'));

        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'display_name' => trim((string) $this->input('display_name')),
            'name' => trim((string) $this->input('name')),
            'can_manage_own_schedule' => $this->boolean('can_manage_own_schedule', true),
            'can_use_ad_hoc_timer' => $this->boolean('can_use_ad_hoc_timer', true),
            'preferred_timezone' => $preferredTimezone === '' ? null : $preferredTimezone,
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
        /** @var Student $student */
        $student = $this->route('student');

        return [
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique(User::class, 'username')->ignore($student->user_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'can_manage_own_schedule' => ['required', 'boolean'],
            'can_use_ad_hoc_timer' => ['required', 'boolean'],
            'preferred_timezone' => ['nullable', 'string', 'max:64'],
            'default_push_up_count' => ['required', 'integer', 'min:0', 'max:1000'],
            'rest_duration_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ];
    }
}
