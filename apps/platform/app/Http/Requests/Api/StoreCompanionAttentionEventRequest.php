<?php

namespace App\Http\Requests\Api;

class StoreCompanionAttentionEventRequest extends CompanionDeviceRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'event_type' => strtolower(trim((string) $this->input('event_type'))),
            'occurred_at' => ($occurredAt = trim((string) $this->input('occurred_at'))) === '' ? null : $occurredAt,
            'payload' => $this->input('payload', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', 'in:look_away,body_missing'],
            'occurred_at' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
            'payload.reason' => ['nullable', 'string', 'max:120'],
            'payload.score' => ['nullable', 'numeric'],
            'payload.away_seconds' => ['nullable', 'numeric', 'min:0'],
            'payload.body_confidence' => ['nullable', 'numeric'],
        ];
    }
}
