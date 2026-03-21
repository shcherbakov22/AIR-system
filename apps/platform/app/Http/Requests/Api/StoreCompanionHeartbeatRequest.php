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
            'remote_control_ready' => filter_var($this->input('remote_control_ready'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'remote_control_active' => filter_var($this->input('remote_control_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'remote_control_port' => ($port = trim((string) $this->input('remote_control_port'))) === '' ? null : (int) $port,
            'remote_control_failure_reason' => ($reason = trim((string) $this->input('remote_control_failure_reason'))) === '' ? null : $reason,
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
            'remote_control_ready' => ['nullable', 'boolean'],
            'remote_control_active' => ['nullable', 'boolean'],
            'remote_control_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'remote_control_failure_reason' => ['nullable', 'string', 'max:2000'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
