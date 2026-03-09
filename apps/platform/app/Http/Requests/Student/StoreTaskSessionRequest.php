<?php

namespace App\Http\Requests\Student;

use App\Models\TaskAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskSessionRequest extends FormRequest
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
        $studentId = $this->user()?->student?->id;

        return [
            'task_assignment_id' => [
                'required',
                'integer',
                Rule::exists(TaskAssignment::class, 'id')->where(
                    fn ($query) => $query
                        ->where('student_id', $studentId)
                        ->where('status', 'assigned')
                ),
            ],
        ];
    }
}
