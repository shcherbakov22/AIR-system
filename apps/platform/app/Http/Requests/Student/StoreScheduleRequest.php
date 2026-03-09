<?php

namespace App\Http\Requests\Student;

class StoreScheduleRequest extends ManagesOwnScheduleRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->scheduleRules();
    }
}
