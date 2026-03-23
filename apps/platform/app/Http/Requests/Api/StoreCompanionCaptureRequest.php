<?php

namespace App\Http\Requests\Api;

class StoreCompanionCaptureRequest extends CompanionDeviceRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'captured_at' => $this->input('captured_at'),
            'app_name' => ($appName = trim((string) $this->input('app_name'))) === '' ? null : $appName,
            'window_title' => ($windowTitle = trim((string) $this->input('window_title'))) === '' ? null : $windowTitle,
            'browser_domain' => ($browserDomain = trim((string) $this->input('browser_domain'))) === '' ? null : $browserDomain,
            'meta' => $this->input('meta', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'capture' => ['required', 'file', 'max:51200'],
            'captured_at' => ['nullable', 'date'],
            'app_name' => ['nullable', 'string', 'max:190'],
            'window_title' => ['nullable', 'string', 'max:255'],
            'browser_domain' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
