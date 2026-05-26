<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StoreCompanionActivityRequest extends CompanionDeviceRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'event_type' => trim((string) $this->input('event_type')),
            'app_name' => ($appName = trim((string) $this->input('app_name'))) === '' ? null : $appName,
            'window_title' => ($windowTitle = trim((string) $this->input('window_title'))) === '' ? null : $windowTitle,
            'browser_domain' => ($browserDomain = trim((string) $this->input('browser_domain'))) === '' ? null : $browserDomain,
            'payload' => $this->input('payload', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::in([
                'focused_app',
                'open_apps',
                'installed_apps',
                'app_enforcement',
                'app_close_attempt',
                'update_check',
                'update_download',
                'extension_status',
                'policy_sync',
                'client_log',
            ])],
            'app_name' => ['nullable', 'string', 'max:190'],
            'window_title' => ['nullable', 'string', 'max:255'],
            'browser_domain' => ['nullable', 'string', 'max:255'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
