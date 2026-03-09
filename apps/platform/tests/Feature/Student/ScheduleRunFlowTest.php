<?php

namespace Tests\Feature\Student;

use App\Enums\ScheduleWeekday;
use App\Enums\UserRole;
use App\Models\ScheduleRun;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleRunFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function createStudent(User $studentUser, string $displayName = 'Schedule Runner'): Student
    {
        return Student::create([
            'user_id' => $studentUser->id,
            'display_name' => $displayName,
            'status' => 'active',
            'notes' => null,
        ]);
    }

    protected function createScheduleTemplate(Student $student): ScheduleTemplate
    {
        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Tuesday Run',
            'weekday' => ScheduleWeekday::Tuesday,
            'is_active' => true,
            'notes' => 'Main weekday schedule.',
            'created_by_user_id' => $student->user_id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => null,
            'task_title' => 'Essay Draft',
            'task_summary' => 'Draft the essay response.',
            'task_instructions' => 'Write until the timer ends.',
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 40,
            'notes' => 'Essay block.',
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => null,
            'task_title' => 'Reading Review',
            'task_summary' => 'Read and summarize the text.',
            'task_instructions' => 'Take notes while you read.',
            'position' => 2,
            'start_time' => '09:45',
            'duration_minutes' => 30,
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
            ->assertSessionHas('success', 'Расписание Tuesday Run запущено.');

        $scheduleRun = ScheduleRun::query()
            ->with('blocks')
            ->where('student_id', $student->id)
            ->sole();

        $this->assertSame('active', $scheduleRun->status);
        $this->assertSame('Tuesday Run', $scheduleRun->schedule_name_snapshot);
        $this->assertCount(2, $scheduleRun->blocks);
        $this->assertSame(['pending', 'pending'], $scheduleRun->blocks->pluck('status')->all());

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('activeScheduleRun.schedule_name', 'Tuesday Run')
                ->where('activeScheduleRun.weekday_label', ScheduleWeekday::Tuesday->label())
                ->where('activeScheduleRun.completed_blocks', 0)
                ->where('activeScheduleRun.total_blocks', 2)
                ->where('activeScheduleRun.next_block.position', 1)
                ->where('activeScheduleRun.blocks.0.task.title', 'Essay Draft')
            );
    }

    public function test_student_can_only_start_schedule_blocks_in_order(): void
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
            ->assertSessionHas('error', 'Запускайте задания расписания по порядку.');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Сессия задания Essay Draft началась.');

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $firstBlock->id,
            'status' => 'active',
            'task_title_snapshot' => 'Essay Draft',
        ]);

        $this->assertDatabaseHas('schedule_run_blocks', [
            'id' => $firstBlock->id,
            'status' => 'in_progress',
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
            ->patch(route('student.task-sessions.stop', $firstTaskSession), [
                'completion_notes' => 'Finished the draft.',
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Сессия задания Essay Draft завершена.');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun->fresh(),
                'scheduleRunBlock' => $secondBlock->fresh(),
            ]))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Сессия задания Reading Review началась.');

        $secondTaskSession = TaskSession::query()->where('student_id', $student->id)->where('status', 'active')->sole();

        Carbon::setTestNow('2026-03-08 10:10:00');

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $secondTaskSession), [
                'completion_notes' => 'Finished the reading review.',
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Сессия задания Reading Review завершена. Расписание Tuesday Run завершено.');

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

    public function test_student_can_pause_a_running_schedule_block_for_an_own_timer_and_resume_it(): void
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

        $this->actingAs($studentUser)
            ->post(route('student.schedule-run-blocks.start', [
                'scheduleRun' => $scheduleRun,
                'scheduleRunBlock' => $firstBlock,
            ]));

        Carbon::setTestNow('2026-03-08 09:12:00');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.pause', $scheduleRun), [
                'task_title' => 'Break Timer',
                'duration_minutes' => 15,
                'notes' => 'Handle an urgent interruption.',
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Расписание Tuesday Run поставлено на паузу. Собственный таймер запущен.');

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
            'status' => 'active',
            'task_title_snapshot' => 'Break Timer',
            'task_summary_snapshot' => 'Handle an urgent interruption.',
            'planned_duration_minutes' => 15,
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
            ->patch(route('student.task-sessions.stop', $adHocTaskSession), [
                'completion_notes' => 'Interruption handled.',
            ])
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Сессия задания Break Timer завершена. Возобновите расписание Tuesday Run, когда будете готовы.');

        $this->actingAs($studentUser)
            ->post(route('student.schedule-runs.resume', $scheduleRun))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Сессия задания Essay Draft возобновлена.');

        Carbon::setTestNow();

        $scheduleRun->refresh();
        $scheduleRun->load('blocks');

        $this->assertSame('active', $scheduleRun->status);
        $this->assertSame('in_progress', $scheduleRun->blocks->firstWhere('position', 1)->status);

        $this->assertDatabaseHas('task_sessions', [
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $firstBlock->id,
            'status' => 'active',
            'task_title_snapshot' => 'Essay Draft',
        ]);
    }
}
