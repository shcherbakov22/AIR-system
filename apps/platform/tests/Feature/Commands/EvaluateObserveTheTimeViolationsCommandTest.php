<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\EvaluateObserveTheTimeViolationsCommand;
use App\Enums\UserRole;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EvaluateObserveTheTimeViolationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_overdue_task_observe_the_time_violation_without_page_load(): void
    {
        Carbon::setTestNow('2026-03-23 10:36:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'observe_command_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Observe Command Student',
            'status' => 'active',
            'notes' => null,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 30,
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->artisan(EvaluateObserveTheTimeViolationsCommand::class)
            ->expectsOutput('Evaluated Observe the time for 1 students.')
            ->assertSuccessful();

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'status' => 'open',
        ]);

        Carbon::setTestNow();
    }

    public function test_command_creates_idle_schedule_observe_the_time_violation_without_page_load(): void
    {
        Carbon::setTestNow('2026-03-23 10:16:00');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $mentor->id,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'observe_idle_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Observe Idle Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'schedule_template_id' => null,
            'schedule_name_snapshot' => 'Morning',
            'schedule_weekday_snapshot' => 'Sunday',
            'schedule_notes_snapshot' => null,
            'status' => 'active',
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Math',
            'planned_duration_minutes' => 10,
            'started_at' => CarbonImmutable::parse('2026-03-23 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-03-23 10:10:00'),
            'duration_seconds' => 600,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->artisan(EvaluateObserveTheTimeViolationsCommand::class)
            ->expectsOutput('Evaluated Observe the time for 1 students.')
            ->assertSuccessful();

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'status' => 'open',
        ]);

        Carbon::setTestNow();
    }
}
