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
            'ipv4' => ($ipv4 = trim((string) $this->input('ipv4'))) === '' ? null : $ipv4,
            'mac_address' => ($mac = trim((string) $this->input('mac_address'))) === '' ? null : strtolower($mac),
            'gateway_ipv4' => ($gateway = trim((string) $this->input('gateway_ipv4'))) === '' ? null : $gateway,
            'network_adapter_name' => ($adapter = trim((string) $this->input('network_adapter_name'))) === '' ? null : $adapter,
            'meta' => $this->input('meta', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:160'],
            'hostname' => ['nullable', 'string', 'max:160'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'ipv4' => ['nullable', 'ip'],
            'mac_address' => ['nullable', 'string', 'max:64'],
            'gateway_ipv4' => ['nullable', 'ip'],
            'network_adapter_name' => ['nullable', 'string', 'max:160'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
