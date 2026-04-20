<?php

namespace App\Http\Requests\Api;

class StoreCompanionBrowserAccessRequest extends CompanionDeviceRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'url' => trim((string) $this->input('url')),
            'reason' => ($reason = trim((string) $this->input('reason'))) === '' ? null : $reason,
        ]);
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
