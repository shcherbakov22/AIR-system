<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_student_schedule_progress(): void
    {
        Carbon::setTestNow('2026-03-12 10:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_progress',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'progress_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Progress Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Focus Day',
            'schedule_weekday_snapshot' => 'Thursday',
            'started_at' => Carbon::parse('2026-03-12 08:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $firstBlock = ScheduleRunBlock::create([
            'schedule_run_id' => $scheduleRun->id,
            'position' => 1,
            'status' => 'completed',
            'start_time_snapshot' => '08:00',
            'duration_minutes_snapshot' => 30,
            'task_title_snapshot' => 'Math',
            'started_at' => Carbon::parse('2026-03-12 08:00:00'),
            'completed_at' => Carbon::parse('2026-03-12 08:30:00'),
        ]);

        $secondBlock = ScheduleRunBlock::create([
            'schedule_run_id' => $scheduleRun->id,
            'position' => 2,
            'status' => 'in_progress',
            'start_time_snapshot' => '08:30',
            'duration_minutes_snapshot' => 40,
            'task_title_snapshot' => 'Reading',
            'started_at' => Carbon::parse('2026-03-12 08:35:00'),
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $firstBlock->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Math',
            'planned_duration_minutes' => 30,
            'started_at' => Carbon::parse('2026-03-12 08:00:00'),
            'ended_at' => Carbon::parse('2026-03-12 08:30:00'),
            'duration_seconds' => 1800,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $secondBlock->id,
            'status' => 'paused',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 40,
            'started_at' => Carbon::parse('2026-03-12 08:35:00'),
            'ended_at' => Carbon::parse('2026-03-12 08:50:00'),
            'duration_seconds' => 900,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $secondBlock->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 40,
            'started_at' => Carbon::parse('2026-03-12 09:00:00'),
            'duration_seconds' => 900,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.progress', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Progress')
                ->where('student.display_name', 'Progress Student')
                ->where('summary.schedule_runs', 1)
                ->where('summary.completed_blocks', 1)
                ->where('summary.total_blocks', 2)
                ->where('summary.active_schedule_name', 'Focus Day')
                ->where('runs.0.schedule_name', 'Focus Day')
                ->where('runs.0.blocks.0.task_title', 'Math')
                ->where('runs.0.blocks.0.actual_duration_seconds', 1800)
                ->where('runs.0.blocks.1.task_title', 'Reading')
                ->where('runs.0.blocks.1.actual_duration_seconds', 5400)
                ->where('runs.0.blocks.1.session_logs.0.status', 'paused')
                ->where('runs.0.blocks.1.session_logs.1.status', 'active')
            );

        Carbon::setTestNow();
    }

    public function test_students_are_redirected_away_from_student_progress(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_progress_blocked',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Blocked Progress Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.students.progress', $student))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
