<?php

namespace Tests\Feature\Student;

use App\Enums\ScheduleWeekday;
use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\Violation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleRunFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function createObserveTheTimeRule(User $admin): RuleDefinition
    {
        return $this->createAutomaticRule($admin, 'Observe the time');
    }

    protected function createSkippedScheduledTaskRule(User $admin): RuleDefinition
    {
        return $this->createAutomaticRule($admin, 'Skipped scheduled task');
    }

    protected function createTaskCompletedTooQuicklyRule(User $admin): RuleDefinition
    {
        return $this->createAutomaticRule($admin, 'Task completed too quickly');
    }

    protected function createAutomaticRule(User $admin, string $title): RuleDefinition
    {
        return RuleDefinition::create([
            'title' => $title,
            'description' => 'Imported automatic rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);
    }

    protected function createStudent(User $studentUser, string $displayName = 'Schedule Runner'): Student
    {
        return Student::create([
            'user_id' => $studentUser->id,
            'display_name' => $displayName,
            'status' => 'active',
            'notes' => null,
        ]);
    }

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
            'can_end_early' => true,
            'can_interrupt_schedule' => true,
            'is_active' => true,
            'created_by_user_id' => $creator->id,
        ]);
    }

    protected function createScheduleTemplate(Student $student): ScheduleTemplate
    {
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $essayTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Essay Draft',
            40,
            'Draft the essay response.',
            'Write until the timer ends.',
        );
        $readingTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Reading Review',
            30,
            'Read and summarize the text.',
            'Take notes while you read.',
        );

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Tuesday Run',
            'weekday' => ScheduleWeekday::Tuesday,
            'notes' => 'Main weekday schedule.',
            'created_by_user_id' => $student->user_id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $essayTemplate->id,
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => $essayTemplate->default_duration_minutes,
            'notes' => 'Essay block.',
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $readingTemplate->id,
            'position' => 2,
            'start_time' => '09:45',
            'duration_minutes' => $readingTemplate->default_duration_minutes,
            'notes' => 'Reading block.',
        ]);

        return $scheduleTemplate->fresh('entries');
    }

    public function test_student_can_start_a_schedule_run_and_see_it_on_the_home_page(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Schedule started.');

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $this->assertSame('active', $scheduleRun->status);
        $this->assertSame('Schedule', $scheduleRun->schedule_name_snapshot);
        $this->assertCount(2, $scheduleRun->blocks);
        $this->assertSame(['pending', 'pending'], $scheduleRun->blocks->pluck('status')->all());

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun.schedule_name', 'Schedule')
                ->where('activeScheduleRun.weekday_label', ScheduleWeekday::Tuesday->label())
                ->where('activeScheduleRun.completed_blocks', 0)
                ->where('activeScheduleRun.total_blocks', 2)
                ->where('activeScheduleRun.next_block.position', 1)
                ->where('activeScheduleRun.blocks.0.task.title', 'Essay Draft')
            );
    }

    public function test_student_home_uses_latest_active_schedule_run_when_a_stale_run_is_still_active(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_latest_student',
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'schedule_run_latest_admin',
        ]);

        $student = $this->createStudent($studentUser, 'Latest Schedule Student');
        $taskTemplate = $this->createTaskTemplate(
            $admin,
            'Current Reading',
            15,
            'Read the current passage.',
            'Keep notes while reading.',
        );

        $oldRun = ScheduleRun::create([
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Old Schedule',
            'schedule_weekday_snapshot' => 'monday',
            'schedule_notes_snapshot' => null,
            'started_at' => Carbon::parse('2026-03-08 09:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $oldBlock = $oldRun->blocks()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'status' => 'completed',
            'start_time_snapshot' => '09:00',
            'duration_minutes_snapshot' => 60,
            'task_title_snapshot' => 'Old Reading',
            'task_summary_snapshot' => 'Old summary.',
            'task_instructions_snapshot' => 'Old instructions.',
            'entry_notes_snapshot' => null,
            'started_at' => Carbon::parse('2026-03-08 09:00:00'),
            'completed_at' => Carbon::parse('2026-03-08 10:00:00'),
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'schedule_run_id' => $oldRun->id,
            'schedule_run_block_id' => $oldBlock->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Old Reading',
            'task_summary_snapshot' => 'Old summary.',
            'task_instructions_snapshot' => 'Old instructions.',
            'planned_duration_minutes' => 60,
            'started_at' => Carbon::parse('2026-03-08 09:00:00'),
            'ended_at' => Carbon::parse('2026-03-08 10:00:00'),
            'duration_seconds' => 3600,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $currentRun = ScheduleRun::create([
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Current Schedule',
            'schedule_weekday_snapshot' => 'monday',
            'schedule_notes_snapshot' => null,
            'started_at' => Carbon::parse('2026-03-08 11:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $currentBlock = $currentRun->blocks()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'status' => 'completed',
            'start_time_snapshot' => '11:00',
            'duration_minutes_snapshot' => 15,
            'task_title_snapshot' => 'Current Reading',
            'task_summary_snapshot' => 'Read the current passage.',
            'task_instructions_snapshot' => 'Keep notes while reading.',
            'entry_notes_snapshot' => null,
            'started_at' => Carbon::parse('2026-03-08 11:00:00'),
            'completed_at' => Carbon::parse('2026-03-08 11:05:00'),
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'schedule_run_id' => $currentRun->id,
            'schedule_run_block_id' => $currentBlock->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Current Reading',
            'task_summary_snapshot' => 'Read the current passage.',
            'task_instructions_snapshot' => 'Keep notes while reading.',
            'planned_duration_minutes' => 15,
            'started_at' => Carbon::parse('2026-03-08 11:00:00'),
            'ended_at' => Carbon::parse('2026-03-08 11:05:00'),
            'duration_seconds' => 300,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun.id', $currentRun->id)
                ->where('activeScheduleRun.schedule_name', 'Current Schedule')
                ->where('activeScheduleRun.blocks.0.task.title', 'Current Reading')
                ->where('activeScheduleRun.blocks.0.actual_duration_label', '05:00')
            );
    }

    public function test_student_can_start_any_pending_schedule_block_out_of_order(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $secondBlock,
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Reading Review'));

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $secondBlock->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading Review',
        ]);

        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $secondBlock->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $firstBlock->id,
            'status' => 'pending',
        ]);
    }

    public function test_stopping_the_last_schedule_block_completes_the_schedule_run(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $firstTaskSession = TaskSession::query()->where('student_id', $student->id)->where('status', 'active')->sole();

        Carbon::setTestNow('2026-03-08 09:40:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $firstTaskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Essay Draft'));

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $secondBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Reading Review'));

        $secondTaskSession = TaskSession::query()->where('student_id', $student->id)->where('status', 'active')->sole();

        Carbon::setTestNow('2026-03-08 10:10:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $secondTaskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Reading Review') && str_contains($message, 'Schedule completed.'));

        Carbon::setTestNow();

        $scheduleRun->refresh();
        $scheduleRun->load('blocks');

        $this->assertSame('completed', $scheduleRun->status);
        $this->assertNotNull($scheduleRun->completed_at);
        $this->assertSame($studentUser->id, $scheduleRun->completed_by_user_id);
        $this->assertSame(['completed', 'completed'], $scheduleRun->blocks->pluck('status')->all());

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun', null)
                ->where('activeTaskSession', null)
            );
    }

    public function test_student_can_start_a_schedule_after_finishing_sleeping(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_from_sleep_student',
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'schedule_from_sleep_admin',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $sleepingTemplate = $this->createTaskTemplate(
            $admin,
            'Sleeping',
            900,
            'Sleep.',
            'Go to sleep.',
        );

        $sleepingSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $sleepingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Sleeping',
            'task_summary_snapshot' => 'Sleep.',
            'task_instructions_snapshot' => 'Go to sleep.',
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => 900,
            'started_at' => Carbon::parse('2026-03-08 08:30:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $sleepingSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Task session Sleeping finished.');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Schedule started.');

        $this->assertDatabaseHas('schedule_runs', [
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Schedule',
        ]);

        $this->assertDatabaseMissing('task_sessions', [
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Sleeping',
        ]);

        Carbon::setTestNow();
    }

    public function test_student_can_finish_a_schedule_with_uncompleted_blocks(): void
    {
        Carbon::setTestNow('2026-03-08 19:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_finish_student',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $sleepingTemplate = $this->createTaskTemplate(
            $catalogOwner = User::factory()->create([
                'role' => UserRole::Admin,
            ]),
            'Sleeping',
            900,
            'Sleep.',
            'Go to sleep.',
        );

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $taskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 19:20:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession));

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.complete', $scheduleRun))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Schedule finished. Sleeping started.');

        $scheduleRun->refresh();
        $scheduleRun->load('blocks');

        $this->assertSame('completed', $scheduleRun->status);
        $this->assertNotNull($scheduleRun->completed_at);
        $this->assertSame($studentUser->id, $scheduleRun->completed_by_user_id);
        $this->assertSame('completed', $scheduleRun->blocks->firstWhere('position', 1)->status);
        $this->assertSame('pending', $scheduleRun->blocks->firstWhere('position', 2)->status);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun', null)
                ->where('activeTaskSession.task_title', 'Sleeping')
            );

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'task_template_id' => $sleepingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Sleeping',
        ]);

        Carbon::setTestNow();
    }

    public function test_student_can_not_finish_a_schedule_before_7_pm(): void
    {
        Carbon::setTestNow('2026-03-08 18:30:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_finish_student',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->where('student_id', $student->id)
            ->sole();

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.complete', $scheduleRun))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', 'Schedules can only be finished manually between 7:00 PM and 8:00 PM.');

        $this->assertDatabaseHas('schedule_runs', [
            'id' => $scheduleRun->id,
            'status' => 'active',
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scheduleFinishWindow.can_finish_now', false)
                ->where('scheduleFinishWindow.opens_at_label', '7:00 PM')
                ->where('scheduleFinishWindow.closes_at_label', '8:00 PM')
            );

        Carbon::setTestNow();
    }

    public function test_student_can_pause_a_running_schedule_block_for_an_own_timer_and_resume_it(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $breakTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Break Timer',
            15,
            'Handle an urgent interruption.',
            'Pause the schedule until the interruption is handled.',
        );

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        Carbon::setTestNow('2026-03-08 09:12:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Schedule paused. Custom timer started.');

        $scheduleRun->refresh();
        $scheduleRun->load('blocks');

        $this->assertSame('paused', $scheduleRun->status);
        $this->assertSame('paused', $scheduleRun->blocks->firstWhere('position', 1)->status);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $firstBlock->id,
            'status' => 'paused',
            'duration_seconds' => 720,
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Break Timer',
            'planned_duration_minutes' => 15,
            'duration_seconds' => 0,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun.status', 'paused')
                ->where('activeScheduleRun.paused_block.position', 1)
                ->where('activeTaskSession.source_type', 'ad_hoc')
                ->where('activeTaskSession.task_title', 'Break Timer')
            );

        $adHocTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:27:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.resume', $scheduleRun))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Essay Draft'));

        $scheduleRun->refresh();
        $scheduleRun->load('blocks');

        $this->assertSame('active', $scheduleRun->status);
        $this->assertSame('in_progress', $scheduleRun->blocks->firstWhere('position', 1)->status);

        $this->assertDatabaseHas('task_sessions', [
            'id' => $adHocTaskSession->id,
            'student_id' => $student->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Break Timer',
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $firstBlock->id,
            'status' => 'active',
            'task_title_snapshot' => 'Essay Draft',
            'planned_duration_minutes' => 40,
            'duration_seconds' => 720,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeTaskSession.task_title', 'Essay Draft')
                ->where('activeTaskSession.duration_seconds', 720)
                ->where('activeTaskSession.planned_duration_minutes', 40)
            );

        Carbon::setTestNow('2026-03-08 09:55:00');

        $resumedTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('schedule_run_id', $scheduleRun->id)
            ->where('schedule_run_block_id', $firstBlock->id)
            ->where('status', 'active')
            ->latest('id')
            ->sole();

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $resumedTaskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Essay Draft'));

        $this->assertDatabaseHas('task_sessions', [
            'id' => $resumedTaskSession->id,
            'status' => 'completed',
            'duration_seconds' => 2400,
        ]);

        Carbon::setTestNow();
    }

    public function test_running_schedule_task_can_only_be_interrupted_by_allowed_task_templates(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_interrupt_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $breakTemplate = TaskTemplate::create([
            'title' => 'Unapproved Break',
            'summary' => 'Not allowed during a running schedule task.',
            'instructions' => 'Wait until no task is active.',
            'default_duration_minutes' => 15,
            'can_interrupt_schedule' => false,
            'created_by_user_id' => $catalogOwner->id,
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();
        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', 'This task is not allowed while interrupting a running schedule task.');

        $this->assertDatabaseMissing('task_sessions', [
            'student_id' => $student->id,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
        ]);

        $breakTemplate->update(['can_interrupt_schedule' => true]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Schedule paused. Custom timer started.');

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
        ]);

        Carbon::setTestNow();
    }

    public function test_schedule_can_pause_to_any_task_when_no_task_is_active(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_pause_no_active_task_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $breakTemplate = TaskTemplate::create([
            'title' => 'General Break',
            'summary' => 'Allowed when no schedule task is running.',
            'instructions' => 'Use only between tasks.',
            'default_duration_minutes' => 10,
            'can_interrupt_schedule' => false,
            'created_by_user_id' => $catalogOwner->id,
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->where('student_id', $student->id)
            ->sole();

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Schedule paused. Custom timer started.');

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
        ]);

        Carbon::setTestNow();
    }

    public function test_task_finish_remains_clickable_before_eighty_percent_and_creates_violation_when_needed(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_eighty_percent_student',
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'too_short_rule_admin',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $scheduledTemplate = $scheduleTemplate->entries()->firstOrFail()->taskTemplate;
        $scheduledTemplate->update(['can_end_early' => false]);
        $this->createObserveTheTimeRule($admin);
        $tooShortRule = $this->createTaskCompletedTooQuicklyRule($admin);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();
        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $taskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:10:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Essay Draft'));

        $this->assertDatabaseHas('task_sessions', [
            'id' => $taskSession->id,
            'status' => 'completed',
            'duration_seconds' => 600,
        ]);

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $tooShortRule->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Task completed too quickly',
            'auto_generated_key' => 'observe-time:too-short:session:'.$taskSession->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_stopping_a_custom_timer_does_not_create_observe_the_time_violation_for_a_paused_schedule(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation',
        ]);
        $breakTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Break Timer',
            15,
            'Handle an urgent interruption.',
            'Pause the schedule until the interruption is handled.',
        );

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($mentor);
        $this->createTaskTemplate(
            $mentor,
            'Sleeping',
            900,
            'Sleep.',
            'Go to sleep.',
        );

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        Carbon::setTestNow('2026-03-08 09:12:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false));

        $adHocTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->whereNull('schedule_run_id')
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:20:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $adHocTaskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseCount('violations', 0);

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 0);

        Carbon::setTestNow();
    }

    public function test_resuming_a_schedule_after_a_custom_timer_with_time_remaining_does_not_create_observe_the_time_violation(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation',
        ]);
        $breakTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Break Timer',
            15,
            'Handle an urgent interruption.',
            'Pause the schedule until the interruption is handled.',
        );

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($mentor);
        $this->createTaskTemplate(
            $mentor,
            'Sleeping',
            900,
            'Sleep.',
            'Go to sleep.',
        );

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        Carbon::setTestNow('2026-03-08 09:12:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false));

        Carbon::setTestNow('2026-03-08 09:20:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.resume', $scheduleRun))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Essay Draft'));

        $this->assertDatabaseCount('violations', 0);

        Carbon::setTestNow();
    }

    public function test_student_can_not_start_a_schedule_run_while_an_open_violation_exists(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $ruleDefinition = RuleDefinition::create([
            'title' => 'Follow the schedule',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 50,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => $ruleDefinition->title,
            'penalty_units' => 50,
            'occurred_at' => '2026-03-08 08:55:00',
            'notes' => 'Open violation blocks schedule progress.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Follow the schedule') && str_contains($message, '08:55'));

        $this->assertDatabaseCount('schedule_runs', 0);
    }

    public function test_student_can_not_start_a_schedule_run_while_unread_mentor_chat_exists_until_chat_is_opened(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_messages',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        ChatMessage::create([
            'student_id' => $student->id,
            'sender_user_id' => $mentor->id,
            'channel' => 'chat',
            'body' => 'Read this before starting.',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'unread mentor chat'));

        $this->assertDatabaseCount('schedule_runs', 0);

        $this->actingAs($studentUser)
            ->get(route('student.chat.show'))
            ->assertOk();

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Schedule started.');
    }

    public function test_student_can_not_start_a_schedule_block_while_unread_announcements_exist_until_announcements_are_opened(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_announcements',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        ChatMessage::create([
            'student_id' => null,
            'sender_user_id' => $mentor->id,
            'channel' => 'announcement',
            'body' => 'Read the announcement before continuing.',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'unread announcement'));

        $this->assertDatabaseCount('task_sessions', 0);

        $this->actingAs($studentUser)
            ->get(route('student.announcements.show'))
            ->assertOk();

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $firstBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Essay Draft'));
    }

    public function test_student_can_not_pause_for_an_own_timer_while_an_open_violation_exists(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_violations',
        ]);
        $breakTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Break Timer',
            15,
            'Handle an urgent interruption.',
            'Pause the schedule until the interruption is handled.',
        );

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Act as planned',
            'description' => 'Imported legacy rule.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 50,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => $ruleDefinition->title,
            'penalty_units' => 50,
            'occurred_at' => '2026-03-08 09:12:00',
            'notes' => 'Open violation blocks pause.',
            'reported_by_user_id' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Act as planned') && str_contains($message, '09:12'));

        Carbon::setTestNow();

        $scheduleRun->refresh();
        $this->assertSame('active', $scheduleRun->status);
        $this->assertDatabaseMissing('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
        ]);
    }

    public function test_idle_schedule_gap_creates_one_automatic_observe_the_time_violation_and_blocks_starting_a_block(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($mentor);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        Carbon::setTestNow('2026-03-08 09:06:00');

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time'));

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'status' => 'open',
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $firstBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time'));

        $this->assertDatabaseCount('violations', 1);

        Carbon::setTestNow();
    }

    public function test_pending_schedule_block_past_its_window_does_not_create_skipped_violation_while_disabled(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_skipped_block_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_skipped_block',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($mentor);
        $this->createSkippedScheduledTaskRule($mentor);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();
        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $taskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:40:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession));

        Carbon::setTestNow('2026-03-08 10:20:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $scheduleRun->refresh();
        $this->assertSame('active', $scheduleRun->status);

        $secondBlock = $scheduleRun->blocks()->where('position', 2)->firstOrFail();
        $this->assertSame('pending', $secondBlock->status);

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Skipped scheduled task',
            'auto_generated_key' => 'observe-time:skipped-block:run:'.$scheduleRun->id.':block:'.$secondBlock->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_resolved_skipped_schedule_block_record_does_not_cascade_into_instant_next_skipped_violation(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_skipped_block_cascade_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_skipped_block_cascade',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($mentor);
        $skippedRule = $this->createSkippedScheduledTaskRule($mentor);
        $thirdTemplate = $this->createTaskTemplate(
            $mentor,
            'Vocabulary Review',
            20,
            'Review vocabulary cards.',
            'Work through the deck.',
        );
        $scheduleTemplate->entries()->create([
            'task_template_id' => $thirdTemplate->id,
            'position' => 3,
            'start_time' => '10:20',
            'duration_minutes' => 20,
            'notes' => 'Vocabulary block.',
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();
        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);
        $thirdBlock = $scheduleRun->blocks->firstWhere('position', 3);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $taskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:40:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession));

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $skippedRule->id,
            'rule_title_snapshot' => 'Skipped scheduled task',
            'status' => 'resolved',
            'penalty_units' => 10,
            'push_up_count' => 10,
            'occurred_at' => Carbon::parse('2026-03-08 10:20:00'),
            'auto_generated_key' => 'observe-time:skipped-block:run:'.$scheduleRun->id.':block:'.$secondBlock->id,
        ]);

        Carbon::setTestNow('2026-03-08 10:50:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Skipped scheduled task',
            'auto_generated_key' => 'observe-time:skipped-block:run:'.$scheduleRun->id.':block:'.$thirdBlock->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_dismissed_skipped_schedule_block_record_suppresses_later_skipped_violations_for_same_run(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_skipped_block_dismissed_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_skipped_block_dismissed',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($mentor);
        $thirdTemplate = $this->createTaskTemplate(
            $mentor,
            'Vocabulary Review',
            20,
            'Review vocabulary cards.',
            'Work through the deck.',
        );
        $scheduleTemplate->entries()->create([
            'task_template_id' => $thirdTemplate->id,
            'position' => 3,
            'start_time' => '10:20',
            'duration_minutes' => 20,
            'notes' => 'Vocabulary block.',
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();
        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);
        $thirdBlock = $scheduleRun->blocks->firstWhere('position', 3);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $taskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:40:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $taskSession));

        DB::table('dismissed_automatic_violations')->insert([
            'student_id' => $student->id,
            'dismissed_by_user_id' => $mentor->id,
            'auto_generated_key' => 'observe-time:skipped-block:run:'.$scheduleRun->id.':block:'.$secondBlock->id,
            'dismissed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Carbon::setTestNow('2026-03-08 10:50:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 0);
        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Skipped scheduled task',
            'auto_generated_key' => 'observe-time:skipped-block:run:'.$scheduleRun->id.':block:'.$thirdBlock->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_completed_schedule_does_not_create_idle_observe_the_time_violation(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($mentor);
        $this->createTaskTemplate(
            $mentor,
            'Sleeping',
            900,
            'Sleep.',
            'Go to sleep.',
        );

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        Carbon::setTestNow('2026-03-08 09:40:00');

        $firstTaskSession = TaskSession::query()->where('student_id', $student->id)->where('status', 'active')->sole();

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $firstTaskSession));

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $secondBlock->fresh(),
            ]));

        Carbon::setTestNow('2026-03-08 10:10:00');

        $secondTaskSession = TaskSession::query()->where('student_id', $student->id)->where('status', 'active')->sole();

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $secondTaskSession));

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Sleeping',
        ]);

        Carbon::setTestNow('2026-03-08 10:20:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 0);

        Carbon::setTestNow();
    }

    public function test_idle_schedule_gap_creates_observe_the_time_violation_once_and_blocks_starting_the_next_block(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_observe_time',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($admin);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $firstTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:40:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $firstTaskSession));

        Carbon::setTestNow('2026-03-08 09:46:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $secondBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time') && str_contains($message, '09:45'));

        $violation = Violation::query()->where('student_id', $student->id)->sole();

        $this->assertSame('Observe the time', $violation->rule_title_snapshot);
        $this->assertSame('open', $violation->status);
        $this->assertSame(10, $violation->penalty_units);
        $this->assertSame(
            'observe-time:idle:run:'.$scheduleRun->id.':anchor:2026-03-08T09:40:00+00:00',
            $violation->auto_generated_key,
        );
        $this->assertSame('2026-03-08T09:45:00+00:00', $violation->occurred_at?->toAtomString());

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $secondBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time') && str_contains($message, '09:45'));

        $this->assertDatabaseCount('violations', 1);

        Carbon::setTestNow();
    }

    public function test_removed_observe_the_time_violation_stays_suppressed_until_a_new_task_is_started(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_observe_time',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($admin);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);
        $secondBlock = $scheduleRun->blocks->firstWhere('position', 2);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $firstTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:40:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $firstTaskSession));

        Carbon::setTestNow('2026-03-08 09:46:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $violation = Violation::query()->where('student_id', $student->id)->sole();

        $this->actingAs($admin)
            ->delete(route('admin.violations.destroy', $violation))
            ->assertRedirect(route('admin.violations.index', absolute: false));

        $this->assertDatabaseCount('violations', 0);
        $this->assertDatabaseHas('dismissed_automatic_violations', [
            'student_id' => $student->id,
            'auto_generated_key' => $violation->auto_generated_key,
        ]);

        Carbon::setTestNow('2026-03-08 09:55:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 0);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $secondBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', fn (?string $message) => is_string($message) && str_contains($message, 'Reading Review'));

        $this->assertDatabaseMissing('dismissed_automatic_violations', [
            'student_id' => $student->id,
            'auto_generated_key' => $violation->auto_generated_key,
        ]);

        Carbon::setTestNow('2026-03-08 10:31:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 1);

        Carbon::setTestNow();
    }

    public function test_completed_ad_hoc_timer_resets_idle_anchor_for_an_active_schedule(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'mentor_auto_violation',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $adHocTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Reading',
            55,
            'Read independently.',
            'Stay on the selected text.',
        );

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($mentor);
        $this->createTaskTemplate(
            $mentor,
            'Sleeping',
            900,
            'Sleep.',
            'Go to sleep.',
        );

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $firstTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:40:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $firstTaskSession));

        TaskSession::create([
            'student_id' => $student->id,
            'task_assignment_id' => null,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => $adHocTemplate->id,
            'status' => 'completed',
            'task_title_snapshot' => $adHocTemplate->title,
            'task_summary_snapshot' => $adHocTemplate->summary,
            'task_instructions_snapshot' => $adHocTemplate->instructions,
            'assignment_notes_snapshot' => null,
            'planned_duration_minutes' => $adHocTemplate->default_duration_minutes,
            'duration_seconds' => 576,
            'started_at' => Carbon::parse('2026-03-08 09:40:20'),
            'ended_at' => Carbon::parse('2026-03-08 09:49:56'),
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        Carbon::setTestNow('2026-03-08 09:53:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 0);

        Carbon::setTestNow('2026-03-08 09:56:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 1);

        Carbon::setTestNow();
    }

    public function test_overdue_schedule_task_creates_observe_the_time_violation_once_and_blocks_pause_for_own_timer(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_observe_time',
        ]);
        $breakTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Break Timer',
            15,
            'Handle an urgent interruption.',
            'Pause the schedule until the interruption is handled.',
        );

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($admin);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        Carbon::setTestNow('2026-03-08 09:46:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time') && str_contains($message, '09:45'));

        $completedTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('schedule_run_id', $scheduleRun->id)
            ->where('schedule_run_block_id', $firstBlock->id)
            ->where('status', 'completed')
            ->sole();
        $violation = Violation::query()->where('student_id', $student->id)->sole();

        $this->assertSame('Observe the time', $violation->rule_title_snapshot);
        $this->assertSame('open', $violation->status);
        $this->assertSame(10, $violation->penalty_units);
        $this->assertSame(
            'observe-time:overtime:block:'.$firstBlock->id,
            $violation->auto_generated_key,
        );
        $this->assertSame('2026-03-08T09:45:00+00:00', $violation->occurred_at?->toAtomString());

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_template_id' => $breakTemplate->id,
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', fn (?string $message) => is_string($message) && str_contains($message, 'Observe the time') && str_contains($message, '09:45'));

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseMissing('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => null,
            'schedule_run_block_id' => null,
            'task_template_id' => $breakTemplate->id,
            'status' => 'active',
        ]);

        Carbon::setTestNow();
    }

    public function test_overdue_schedule_task_does_not_chain_into_idle_observe_the_time_without_starting_a_new_task(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student_no_chain',
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_observe_time_no_chain',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($admin);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        Carbon::setTestNow('2026-03-08 09:46:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $firstViolation = Violation::query()
            ->where('student_id', $student->id)
            ->sole();

        $this->assertSame(
            'observe-time:overtime:block:'.$firstBlock->id,
            $firstViolation->auto_generated_key,
        );

        Carbon::setTestNow('2026-03-08 09:55:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'auto_generated_key' => 'observe-time:idle:run:'.$scheduleRun->id.':anchor:2026-03-08T09:46:00+00:00',
        ]);

        Carbon::setTestNow();
    }

    public function test_observe_the_time_does_not_appear_while_a_different_violation_type_is_open(): void
    {
        Carbon::setTestNow('2026-03-08 09:00:00');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_run_student_existing_violation',
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_observe_time_existing_violation',
        ]);

        $student = $this->createStudent($studentUser);
        $scheduleTemplate = $this->createScheduleTemplate($student);
        $this->createObserveTheTimeRule($admin);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.store', $scheduleTemplate));

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $firstBlock = $scheduleRun->blocks->firstWhere('position', 1);

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        $activeTaskSession = TaskSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->sole();

        Carbon::setTestNow('2026-03-08 09:40:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $activeTaskSession));

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => null,
            'rule_title_snapshot' => 'Task completed too quickly',
            'status' => 'open',
            'penalty_units' => 10,
            'push_up_count' => 10,
            'occurred_at' => now(),
            'auto_generated_key' => 'observe-time:too-short:session:999',
        ]);

        Carbon::setTestNow('2026-03-08 09:46:00');

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk();

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Observe the time',
            'auto_generated_key' => 'observe-time:idle:run:'.$scheduleRun->id.':anchor:2026-03-08T09:40:00+00:00',
        ]);

        Carbon::setTestNow();
    }
}
