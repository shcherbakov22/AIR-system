<?php

namespace App\Http\Requests\Student;

use App\Models\ScheduleTemplate;

class UpdateScheduleRequest extends ManagesOwnScheduleRequest
{
    public function authorize(): bool
    {
        /** @var ScheduleTemplate|null $scheduleTemplate */
        $scheduleTemplate = $this->route('scheduleTemplate');
        $studentId = $this->user()?->student?->id;

        return ($this->user()?->isStudent() ?? false)
            && $studentId !== null
            && $scheduleTemplate?->student_id === $studentId;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ScheduleTemplate|null $scheduleTemplate */
        $scheduleTemplate = $this->route('scheduleTemplate');

        return $this->scheduleRules(
            currentTaskTemplateIds: $scheduleTemplate?->entries()->pluck('task_template_id')->all() ?? [],
            ignoreScheduleTemplateId: $scheduleTemplate?->id,
        );
    }
}
