<?php

namespace Tests\Feature\Student;

use App\Enums\UserRole;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function createTaskTemplate(
        User $creator,
        string $title,
        int $defaultDurationMinutes,
        string $summary,
        string $instructions,
        bool $isActive = true,
    ): TaskTemplate {
        return TaskTemplate::create([
            'title' => $title,
            'summary' => $summary,
            'instructions' => $instructions,
            'default_duration_minutes' => $defaultDurationMinutes,
            'is_active' => $isActive,
            'created_by_user_id' => $creator->id,
        ]);
    }

    public function test_student_can_create_their_own_schedule_with_multiple_blocks(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $readingTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Reading Review',
            35,
            'Read the selected chapter.',
            'Take notes while you read.',
        );
        $writingTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Writing Sprint',
            25,
            'Draft the short response.',
            'Work until the timer ends.',
        );

        $response = $this->actingAs($studentUser)
            ->post(route('student.schedules.store'), [
                'name' => 'Monday Plan',
                'is_active' => true,
                'notes' => 'Core morning plan.',
                'entries' => [
                    [
                        'task_template_id' => $readingTemplate->id,
                        'notes' => 'Start with reading.',
                    ],
                    [
                        'task_template_id' => $writingTemplate->id,
                        'notes' => 'Move into writing.',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('student.schedules.index', absolute: false))
            ->assertSessionHas('success', 'Расписание Monday Plan сохранено.');

        $scheduleTemplate = ScheduleTemplate::query()
            ->with('entries.taskTemplate')
            ->where('student_id', $student->id)
            ->sole();

        $this->assertSame('Monday Plan', $scheduleTemplate->name);
        $this->assertSame('monday', $scheduleTemplate->weekday);
        $this->assertTrue($scheduleTemplate->is_active);
        $this->assertSame('Core morning plan.', $scheduleTemplate->notes);
        $this->assertCount(2, $scheduleTemplate->entries);
        $this->assertSame(
            [$readingTemplate->id, $writingTemplate->id],
            $scheduleTemplate->entries->pluck('task_template_id')->all(),
        );
        $this->assertSame(
            ['Reading Review', 'Writing Sprint'],
            $scheduleTemplate->entries->map(fn ($entry) => $entry->resolvedTaskTitle())->all(),
        );
        $this->assertSame([35, 25], $scheduleTemplate->entries->pluck('duration_minutes')->all());
        $this->assertSame(
            ['09:00', '09:35'],
            $scheduleTemplate->entries->pluck('start_time')->map(fn ($time) => (string) $time)->all(),
        );
    }

    public function test_student_can_update_their_own_schedule(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $readingTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Reading Review',
            35,
            'Read the selected chapter.',
            'Take notes while you read.',
        );
        $writingTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Writing Sprint',
            25,
            'Draft the short response.',
            'Write until the timer ends.',
        );

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Plan',
            'weekday' => 'monday',
            'is_active' => true,
            'notes' => 'Original note.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $readingTemplate->id,
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => $readingTemplate->default_duration_minutes,
            'notes' => 'Original block.',
        ]);

        $response = $this->actingAs($studentUser)
            ->put(route('student.schedules.update', $scheduleTemplate), [
                'name' => 'Tuesday Plan',
                'is_active' => false,
                'notes' => 'Updated note.',
                'entries' => [
                    [
                        'task_template_id' => $writingTemplate->id,
                        'notes' => 'Writing block.',
                    ],
                    [
                        'task_template_id' => $readingTemplate->id,
                        'notes' => 'Reading follow-up.',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('student.schedules.index', absolute: false))
            ->assertSessionHas('success', 'Расписание Tuesday Plan обновлено.');

        $scheduleTemplate->refresh();
        $scheduleTemplate->load('entries.taskTemplate');

        $this->assertSame('Tuesday Plan', $scheduleTemplate->name);
        $this->assertSame('monday', $scheduleTemplate->weekday);
        $this->assertFalse($scheduleTemplate->is_active);
        $this->assertSame('Updated note.', $scheduleTemplate->notes);
        $this->assertCount(2, $scheduleTemplate->entries);
        $this->assertSame(
            [$writingTemplate->id, $readingTemplate->id],
            $scheduleTemplate->entries->pluck('task_template_id')->all(),
        );
        $this->assertSame(
            ['Writing Sprint', 'Reading Review'],
            $scheduleTemplate->entries->map(fn ($entry) => $entry->resolvedTaskTitle())->all(),
        );
        $this->assertSame([25, 35], $scheduleTemplate->entries->pluck('duration_minutes')->all());
        $this->assertSame(
            ['09:00', '09:25'],
            $scheduleTemplate->entries->pluck('start_time')->map(fn ($time) => (string) $time)->all(),
        );
    }

    public function test_student_can_delete_their_own_schedule(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $readingTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Reading Review',
            35,
            'Read the selected chapter.',
            'Take notes while you read.',
        );

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Plan',
            'weekday' => 'monday',
            'is_active' => true,
            'notes' => 'Original note.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $readingTemplate->id,
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => $readingTemplate->default_duration_minutes,
            'notes' => 'Reading block.',
        ]);

        $this->actingAs($studentUser)
            ->delete(route('student.schedules.destroy', $scheduleTemplate))
            ->assertRedirect(route('student.schedules.index', absolute: false))
            ->assertSessionHas('success', 'Расписание Monday Plan удалено.');

        $this->assertDatabaseMissing('schedule_templates', [
            'id' => $scheduleTemplate->id,
        ]);
    }

    public function test_student_can_not_open_update_or_delete_another_students_schedule(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_student',
        ]);

        $otherStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_other_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $otherStudent = Student::create([
            'user_id' => $otherStudentUser->id,
            'display_name' => 'Other Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $readingTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Reading Review',
            35,
            'Read the selected chapter.',
            'Take notes while you read.',
        );

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $otherStudent->id,
            'name' => 'Private Plan',
            'weekday' => 'monday',
            'is_active' => true,
            'notes' => null,
            'created_by_user_id' => $otherStudentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $readingTemplate->id,
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => $readingTemplate->default_duration_minutes,
            'notes' => 'Private block.',
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.schedules.edit', $scheduleTemplate))
            ->assertNotFound();

        $this->actingAs($studentUser)
            ->put(route('student.schedules.update', $scheduleTemplate), [
                'name' => 'Stolen Plan',
                'is_active' => true,
                'notes' => null,
                'entries' => [
                    [
                        'task_template_id' => $readingTemplate->id,
                        'notes' => null,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->delete(route('student.schedules.destroy', $scheduleTemplate))
            ->assertNotFound();
    }

    public function test_student_can_view_their_schedule_index_create_and_edit_screens(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_student',
        ]);
        $catalogOwner = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Reading Review',
            35,
            'Read the selected chapter.',
            'Take notes while you read.',
        );

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Plan',
            'weekday' => 'monday',
            'is_active' => true,
            'notes' => 'Visible to the student.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => $taskTemplate->default_duration_minutes,
            'notes' => 'Reading block.',
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.schedules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Schedules/Index')
                ->has('scheduleTemplates', 1)
                ->where('scheduleTemplates.0.name', 'Monday Plan')
                ->where('scheduleTemplates.0.entries.0.task.title', 'Reading Review')
            );

        $this->actingAs($studentUser)
            ->get(route('student.schedules.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Schedules/Create')
                ->has('taskTemplates', 1)
                ->where('taskTemplates.0.id', $taskTemplate->id)
                ->where('taskTemplates.0.default_duration_minutes', 35)
                ->missing('weekdays')
            );

        $this->actingAs($studentUser)
            ->get(route('student.schedules.edit', $scheduleTemplate))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Schedules/Edit')
                ->where('scheduleTemplate.name', 'Monday Plan')
                ->has('taskTemplates', 1)
                ->where('taskTemplates.0.id', $taskTemplate->id)
                ->where('scheduleTemplate.entries.0.task_template_id', fn ($value) => (string) $value === (string) $taskTemplate->id)
            );
    }
}
