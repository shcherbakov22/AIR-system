<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEdgeClientHeartbeatRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_key' => trim((string) $this->input('client_key')),
            'client_type' => trim((string) $this->input('client_type')),
            'label' => trim((string) $this->input('label')),
            'version' => ($version = trim((string) $this->input('version'))) === '' ? null : $version,
            'capabilities' => $this->input('capabilities', []),
            'meta' => $this->input('meta', []),
        ]);
    }

    public function authorize(): bool
    {
        $configuredToken = (string) config('services.edge_clients.shared_token');
        $providedToken = (string) $this->header('X-Edge-Client-Token');

        return $configuredToken !== '' && hash_equals($configuredToken, $providedToken);
    }

    public function rules(): array
    {
        return [
            'client_key' => ['required', 'string', 'max:120'],
            'client_type' => ['required', 'string', Rule::in(['browser_extension', 'hardware_bridge'])],
            'label' => ['required', 'string', 'max:160'],
            'version' => ['nullable', 'string', 'max:64'],
            'capabilities' => ['nullable', 'array', 'max:30'],
            'capabilities.*' => ['string', 'max:80'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
