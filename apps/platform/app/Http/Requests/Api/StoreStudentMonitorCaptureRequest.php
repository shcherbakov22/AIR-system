<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentMonitorCaptureRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $username = trim((string) ($this->input('username') ?: $this->input('student_username')));
        $clientKey = trim((string) $this->input('client_key'));
        $clientType = trim((string) $this->input('client_type'));
        $label = trim((string) $this->input('label'));
        $version = trim((string) $this->input('version'));

        $this->merge([
            'username' => $username === '' ? null : $username,
            'client_key' => $clientKey === '' ? null : $clientKey,
            'client_type' => $clientType === '' ? null : $clientType,
            'label' => $label === '' ? null : $label,
            'version' => $version === '' ? null : $version,
            'meta' => $this->input('meta', []),
        ]);
    }

    public function authorize(): bool
    {
        $configuredToken = (string) config('services.edge_clients.shared_token');
        $providedToken = (string) $this->header('X-Edge-Client-Token');

        return $configuredToken !== '' && hash_equals($configuredToken, $providedToken);
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'max:120',
                Rule::exists('users', 'username')->where(
                    fn ($query) => $query->where('role', UserRole::Student->value),
                ),
            ],
            'client_key' => ['nullable', 'string', 'max:120'],
            'client_type' => ['nullable', 'string', Rule::in(['browser_extension', 'hardware_bridge']), 'required_with:client_key'],
            'label' => ['nullable', 'string', 'max:160', 'required_with:client_key'],
            'version' => ['nullable', 'string', 'max:64'],
            'captured_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
            'capture' => ['nullable', 'file', 'image', 'max:15360', 'required_without_all:image,filename'],
            'image' => ['nullable', 'file', 'image', 'max:15360', 'required_without_all:capture,filename'],
            'filename' => ['nullable', 'file', 'image', 'max:15360', 'required_without_all:capture,image'],
        ];
    }
}
