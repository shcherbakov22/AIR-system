<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StartAttentionCalibrationSessionRequest extends CompanionDeviceRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => ($provider = trim((string) $this->input('provider', 'eyetheia'))) === '' ? 'eyetheia' : strtolower($provider),
            'meta' => $this->input('meta', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['eyetheia'])],
            'meta' => ['nullable', 'array'],
        ];
    }
}
