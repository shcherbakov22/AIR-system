<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $instructions = trim((string) $this->input('instructions'));

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'instructions' => $instructions === '' ? null : $instructions,
            'requires_internet' => $this->boolean('requires_internet'),
            'browser_allowed_domains' => collect($this->input('browser_allowed_domains', []))
                ->when(
                    is_string($this->input('browser_allowed_domains')),
                    fn ($domains) => collect(preg_split('/[\r\n,]+/', (string) $this->input('browser_allowed_domains')) ?: [])
                )
                ->map(fn ($domain) => trim((string) $domain))
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'default_duration_minutes' => ['required', 'integer', 'min:1', 'max:10000'],
            'requires_internet' => ['required', 'boolean'],
            'browser_allowed_domains' => ['nullable', 'array'],
            'browser_allowed_domains.*' => ['string', 'max:255'],
        ];
    }
}
