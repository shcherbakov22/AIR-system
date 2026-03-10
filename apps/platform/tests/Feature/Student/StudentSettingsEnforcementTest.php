<?php

namespace Tests\Feature\Student;

use App\Enums\ScheduleWeekday;
use App\Enums\UserRole;
use App\Models\ScheduleRun;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\StudentSetting;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSettingsEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function createTaskTemplate(
        User $creator,
        string $title,
        int $defaultDurationMinutes,
        string $summary,
        string $instructions,
    ): TaskTemplate {
        return TaskTemplate::create([
            'title' => $title,
            'summary' => $summary,
            'instructions' => $instructions,
            'default_duration_minutes' => $defaultDurationMinutes,
            'is_active' => true,
            'created_by_user_id' => $creator->id,
        ]);
    }

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
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
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

        $readingTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Reading',
            30,
            'Read the text.',
            'Read carefully.',
        );
        $breakTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Break Timer',
            15,
            'Short interruption timer.',
            'Use this timer while the schedule is paused.',
        );

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Plan',
            'weekday' => ScheduleWeekday::Monday,
            'is_active' => true,
            'notes' => null,
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $readingTemplate->id,
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => $readingTemplate->default_duration_minutes,
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
                'task_template_id' => $breakTemplate->id,
                'notes' => 'Should not start.',
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', 'Собственные таймеры для этого ученика отключены.');
        $this->assertDatabaseMissing('task_sessions', [
            'student_id' => $student->id,
            'task_template_id' => $breakTemplate->id,
            'schedule_run_id' => null,
        ]);
    }
}
