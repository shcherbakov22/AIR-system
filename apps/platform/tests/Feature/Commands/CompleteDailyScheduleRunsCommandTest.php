<?php

namespace Tests\Feature\Commands;

use App\Enums\ScheduleWeekday;
use App\Enums\UserRole;
use App\Models\ScheduleRun;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CompleteDailyScheduleRunsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_completes_open_schedule_runs_and_active_task_sessions(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);
        $creator = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $taskTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => 'Code.',
            'instructions' => 'Keep coding.',
            'default_duration_minutes' => 55,
            'is_active' => true,
            'created_by_user_id' => $creator->id,
        ]);
        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Daily plan',
            'weekday' => ScheduleWeekday::Monday,
            'notes' => null,
            'created_by_user_id' => $studentUser->id,
        ]);
        $entry = $scheduleTemplate->entries()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 55,
            'notes' => null,
        ]);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'schedule_template_id' => $scheduleTemplate->id,
            'status' => 'paused',
            'schedule_name_snapshot' => 'Daily plan',
            'schedule_weekday_snapshot' => 'Monday',
            'schedule_notes_snapshot' => null,
            'started_at' => now()->setTime(9, 0),
            'started_by_user_id' => $studentUser->id,
        ]);

        $block = $scheduleRun->blocks()->create([
            'schedule_entry_id' => $entry->id,
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'status' => 'paused',
            'start_time_snapshot' => '09:00',
            'duration_minutes_snapshot' => 55,
            'task_title_snapshot' => 'Coding',
            'task_summary_snapshot' => 'Code.',
            'task_instructions_snapshot' => 'Keep coding.',
            'entry_notes_snapshot' => null,
        ]);

        $scheduleTaskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $block->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'paused',
            'task_title_snapshot' => 'Coding',
            'task_summary_snapshot' => 'Code.',
            'task_instructions_snapshot' => 'Keep coding.',
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 55,
            'duration_seconds' => 1800,
            'started_at' => now()->setTime(9, 0),
            'ended_at' => now()->setTime(9, 30),
            'started_by_user_id' => $studentUser->id,
        ]);

        $adHocTaskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'task_summary_snapshot' => 'Read.',
            'task_instructions_snapshot' => 'Keep reading.',
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 55,
            'duration_seconds' => 300,
            'started_at' => now()->setTime(19, 50),
            'started_by_user_id' => $studentUser->id,
        ]);

        Artisan::call('schedule-runs:complete-daily-open');

        $this->assertDatabaseHas('schedule_runs', [
            'id' => $scheduleRun->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $block->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'id' => $scheduleTaskSession->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'id' => $adHocTaskSession->id,
            'status' => 'completed',
            'completion_notes' => 'Automatically finished at 20:00 end of day.',
        ]);
    }
}
