<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class CompanionEnrollmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => trim((string) $this->input('username')),
            'password' => (string) $this->input('password'),
            'device_key' => trim((string) $this->input('device_key')),
            'label' => trim((string) $this->input('label')),
            'hostname' => ($hostname = trim((string) $this->input('hostname'))) === '' ? null : $hostname,
            'platform' => trim((string) $this->input('platform')),
            'app_version' => ($version = trim((string) $this->input('app_version'))) === '' ? null : $version,
            'meta' => $this->input('meta', []),
        ]);
    }

    public function authorize(): bool
    {
        $user = User::query()
            ->where('username', $this->string('username')->toString())
            ->first();

        return $user?->isStudent() === true
            && Hash::check((string) $this->input('password'), $user->password);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
            'device_key' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:160'],
            'hostname' => ['nullable', 'string', 'max:160'],
            'platform' => ['required', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
