<?php

namespace Tests\Feature\Student;

use App\Enums\ScheduleWeekday;
use App\Enums\UserRole;
use App\Models\ScheduleRun;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\StudentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSettingsEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_be_blocked_from_schedule_management_routes(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_settings_blocked',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Blocked Student',
            'status' => 'active',
            'notes' => null,
        ]);

        StudentSetting::create([
            'student_id' => $student->id,
            'can_manage_own_schedule' => false,
            'can_use_ad_hoc_timer' => true,
            'preferred_timezone' => 'UTC',
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.schedules.index'))
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->get(route('student.schedules.create'))
            ->assertForbidden();
    }

    public function test_student_can_be_blocked_from_own_timers_while_still_running_a_schedule(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_no_timer',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'No Timer Student',
            'status' => 'active',
            'notes' => null,
        ]);

        StudentSetting::create([
            'student_id' => $student->id,
            'can_manage_own_schedule' => true,
            'can_use_ad_hoc_timer' => false,
            'preferred_timezone' => 'UTC',
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Plan',
            'weekday' => ScheduleWeekday::Monday,
            'is_active' => true,
            'notes' => null,
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => null,
            'task_title' => 'Reading',
            'task_summary' => 'Read the text.',
            'task_instructions' => 'Read carefully.',
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'notes' => null,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate))
            ->assertRedirect(route('student.home', absolute: false));

        $scheduleRun = ScheduleRun::query()->where('student_id', $student->id)->sole();
        $firstBlock = $scheduleRun->blocks()->where('position', 1)->firstOrFail();

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]))
            ->assertRedirect(route('student.home', absolute: false));

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_title' => 'Break Timer',
                'duration_minutes' => 15,
                'notes' => 'Should not start.',
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', 'Собственные таймеры для этого ученика отключены.');
    }
}
