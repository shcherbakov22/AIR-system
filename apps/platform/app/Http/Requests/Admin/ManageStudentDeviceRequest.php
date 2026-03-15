<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageStudentDeviceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => ($label = trim((string) $this->input('label'))) === '' ? null : $label,
            'command_type' => ($commandType = trim((string) $this->input('command_type'))) === '' ? null : $commandType,
            'payload' => $this->input('payload', []),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:160'],
            'command_type' => ['nullable', Rule::in(['refresh_policy', 'request_screenshot', 'request_camera_capture', 'lock_internet', 'unlock_internet'])],
            'payload' => ['nullable', 'array'],
        ];
    }
}
