<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:50000'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (! filled($this->input('body')) && ! $this->hasFile('attachment')) {
                    $validator->errors()->add('body', 'Write a message or attach a file.');
                }
            },
        ];
    }
}
