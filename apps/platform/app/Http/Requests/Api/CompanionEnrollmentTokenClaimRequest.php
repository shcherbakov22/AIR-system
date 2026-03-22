<?php

namespace App\Http\Requests\Api;

use App\Models\DeviceEnrollmentToken;
use Illuminate\Foundation\Http\FormRequest;

class CompanionEnrollmentTokenClaimRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'enrollment_token' => trim((string) $this->input('enrollment_token')),
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
        return DeviceEnrollmentToken::findActiveByPlainTextToken(
            $this->string('enrollment_token')->toString()
        ) !== null;
    }

    public function rules(): array
    {
        return [
            'enrollment_token' => ['required', 'string', 'max:255'],
            'device_key' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:160'],
            'hostname' => ['nullable', 'string', 'max:160'],
            'platform' => ['required', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
