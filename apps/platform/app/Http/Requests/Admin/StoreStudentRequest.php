<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStudentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $email = trim((string) $this->input('email'));
        $preferredTimezone = trim((string) $this->input('preferred_timezone'));
        $consequenceNotes = trim((string) $this->input('consequence_notes'));

        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'email' => $email === '' ? null : strtolower($email),
            'display_name' => trim((string) $this->input('display_name')),
            'name' => trim((string) $this->input('name')),
            'notes' => trim((string) $this->input('notes')),
            'is_active' => $this->boolean('is_active', true),
            'can_manage_own_schedule' => $this->boolean('can_manage_own_schedule', true),
            'can_use_ad_hoc_timer' => $this->boolean('can_use_ad_hoc_timer', true),
            'preferred_timezone' => $preferredTimezone === '' ? null : $preferredTimezone,
            'consequence_notes' => $consequenceNotes === '' ? null : $consequenceNotes,
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
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique(User::class, 'username'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'password' => ['required', 'string', Password::defaults()],
            'status' => ['required', 'string', Rule::in(['active', 'paused'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'can_manage_own_schedule' => ['required', 'boolean'],
            'can_use_ad_hoc_timer' => ['required', 'boolean'],
            'preferred_timezone' => ['nullable', 'string', 'max:64'],
            'default_push_up_count' => ['required', 'integer', 'min:0', 'max:1000'],
            'rest_duration_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'consequence_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
