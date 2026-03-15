<?php

namespace App\Http\Requests\Api;

class StoreCompanionHeartbeatRequest extends CompanionDeviceRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => ($label = trim((string) $this->input('label'))) === '' ? null : $label,
            'hostname' => ($hostname = trim((string) $this->input('hostname'))) === '' ? null : $hostname,
            'app_version' => ($version = trim((string) $this->input('app_version'))) === '' ? null : $version,
            'meta' => $this->input('meta', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:160'],
            'hostname' => ['nullable', 'string', 'max:160'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
