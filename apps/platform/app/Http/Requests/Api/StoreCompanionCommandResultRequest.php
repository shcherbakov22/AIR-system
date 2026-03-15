<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StoreCompanionCommandResultRequest extends CompanionDeviceRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => trim((string) $this->input('status')),
            'payload' => $this->input('payload', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['completed', 'failed'])],
            'payload' => ['nullable', 'array'],
        ];
    }
}
