<?php

namespace App\Http\Requests\Api;

class StoreAttentionCalibrationBatchRequest extends CompanionDeviceRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'samples' => $this->input('samples', []),
            'captured_at' => ($capturedAt = trim((string) $this->input('captured_at'))) === '' ? null : $capturedAt,
            'finalize' => filter_var($this->input('finalize'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'meta' => $this->input('meta', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'samples' => ['required', 'array', 'min:1', 'max:500'],
            'samples.*.timestamp_ms' => ['nullable', 'numeric'],
            'samples.*.pose' => ['nullable', 'array'],
            'samples.*.eyes' => ['nullable', 'array'],
            'samples.*.screen_target' => ['nullable', 'array'],
            'captured_at' => ['nullable', 'date'],
            'finalize' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
