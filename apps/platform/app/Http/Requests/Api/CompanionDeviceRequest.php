<?php

namespace App\Http\Requests\Api;

use App\Models\StudentDevice;
use Illuminate\Foundation\Http\FormRequest;

class CompanionDeviceRequest extends FormRequest
{
    protected ?StudentDevice $resolvedDevice = null;

    public function authorize(): bool
    {
        $authorization = (string) $this->header('Authorization');
        $token = preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1
            ? trim((string) $matches[1])
            : '';

        $this->resolvedDevice = StudentDevice::findByPlainTextToken($token);

        return $this->resolvedDevice !== null;
    }

    public function device(): StudentDevice
    {
        return $this->resolvedDevice
            ?? throw new \RuntimeException('Companion device was not resolved.');
    }

    public function rules(): array
    {
        return [];
    }
}
