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
            ->assertSessionHas('success', 'Schedule saved.');

        $scheduleTemplate = ScheduleTemplate::query()
            ->with('entries.taskTemplate')
            ->where('student_id', $student->id)
            ->sole();

        $this->assertSame(ScheduleTemplate::DEFAULT_NAME, $scheduleTemplate->name);
        $this->assertSame('monday', $scheduleTemplate->weekday);
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

    public function test_student_can_not_replace_their_existing_schedule_after_creation(): void
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

        $firstTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Reading Review',
            35,
            'Read the selected chapter.',
            'Take notes while you read.',
        );
        $secondTemplate = $this->createTaskTemplate(
            $catalogOwner,
            'Writing Sprint',
            25,
            'Draft the short response.',
            'Write until the timer ends.',
        );

        $this->actingAs($studentUser)
            ->post(route('student.schedules.store'), [
                'notes' => null,
                'entries' => [
                    [
                        'task_template_id' => $firstTemplate->id,
                        'notes' => null,
                    ],
                ],
            ])
            ->assertRedirect(route('student.schedules.index', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('student.schedules.create'))
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->post(route('student.schedules.store'), [
                'notes' => null,
                'entries' => [
                    [
                        'task_template_id' => $secondTemplate->id,
                        'notes' => null,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(1, ScheduleTemplate::query()->where('student_id', $student->id)->count());
        $this->assertSame(
            [$firstTemplate->id],
            ScheduleTemplate::query()
                ->where('student_id', $student->id)
                ->firstOrFail()
                ->entries()
                ->pluck('task_template_id')
                ->all(),
        );
    }

    public function test_student_can_not_edit_their_own_schedule_after_creation(): void
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

        $this->actingAs($studentUser)
            ->get(route('student.schedules.edit', $scheduleTemplate))
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->put(route('student.schedules.update', $scheduleTemplate), [
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
            ])
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->delete(route('student.schedules.destroy', $scheduleTemplate))
            ->assertForbidden();

        $scheduleTemplate->refresh();
        $scheduleTemplate->load('entries.taskTemplate');

        $this->assertSame('Monday Plan', $scheduleTemplate->name);
        $this->assertSame('Original note.', $scheduleTemplate->notes);
        $this->assertCount(1, $scheduleTemplate->entries);
        $this->assertSame([$readingTemplate->id], $scheduleTemplate->entries->pluck('task_template_id')->all());
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

    public function test_student_can_view_their_schedule_index_and_create_screens_but_not_edit_screen(): void
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
                ->where('scheduleTemplates.0.entries.0.task.title', 'Reading Review')
            );

        $this->actingAs($studentUser)
            ->get(route('student.schedules.create'))
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->get(route('student.schedules.edit', $scheduleTemplate))
            ->assertForbidden();
    }
}
