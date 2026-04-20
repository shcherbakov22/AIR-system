<?php

namespace App\Http\Requests\Api;

class StoreCompanionBrowserVisitRequest extends CompanionDeviceRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'url' => trim((string) $this->input('url')),
            'page_title' => ($title = trim((string) $this->input('page_title'))) === '' ? null : $title,
            'meta' => $this->input('meta', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
