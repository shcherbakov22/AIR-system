<?php

namespace App\Http\Requests\Api;

class StoreCompanionBrowserSessionRequest extends CompanionDeviceRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'redirect_path' => ($redirectPath = trim((string) $this->input('redirect_path'))) === '' ? null : $redirectPath,
        ]);
    }

    public function rules(): array
    {
        return [
            'redirect_path' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
