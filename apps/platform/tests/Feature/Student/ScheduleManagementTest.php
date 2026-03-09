<?php

namespace Tests\Feature\Student;

use App\Enums\ScheduleWeekday;
use App\Enums\UserRole;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_their_own_schedule_with_multiple_blocks(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $response = $this->actingAs($studentUser)
            ->post(route('student.schedules.store'), [
                'name' => 'Monday Plan',
                'weekday' => ScheduleWeekday::Monday->value,
                'is_active' => true,
                'notes' => 'Core morning plan.',
                'entries' => [
                    [
                        'task_title' => 'Reading Review',
                        'task_summary' => 'Read the selected chapter.',
                        'task_instructions' => 'Take notes while you read.',
                        'start_time' => '09:00',
                        'duration_minutes' => 35,
                        'notes' => 'Start with reading.',
                    ],
                    [
                        'task_title' => 'Writing Sprint',
                        'task_summary' => 'Draft the short response.',
                        'task_instructions' => 'Work until the timer ends.',
                        'start_time' => '09:40',
                        'duration_minutes' => 25,
                        'notes' => 'Move into writing.',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('student.schedules.index', absolute: false))
            ->assertSessionHas('success', 'Расписание Monday Plan сохранено.');

        $scheduleTemplate = ScheduleTemplate::query()
            ->with('entries')
            ->where('student_id', $student->id)
            ->sole();

        $this->assertSame('Monday Plan', $scheduleTemplate->name);
        $this->assertSame(ScheduleWeekday::Monday, $scheduleTemplate->weekday);
        $this->assertTrue($scheduleTemplate->is_active);
        $this->assertSame('Core morning plan.', $scheduleTemplate->notes);
        $this->assertCount(2, $scheduleTemplate->entries);
        $this->assertSame(
            ['Reading Review', 'Writing Sprint'],
            $scheduleTemplate->entries->pluck('task_title')->all(),
        );
        $this->assertSame(
            ['09:00', '09:40'],
            $scheduleTemplate->entries->pluck('start_time')->map(fn ($time) => (string) $time)->all(),
        );
    }

    public function test_student_can_update_their_own_schedule(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Plan',
            'weekday' => ScheduleWeekday::Monday,
            'is_active' => true,
            'notes' => 'Original note.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => null,
            'task_title' => 'Reading Review',
            'task_summary' => 'Read the selected chapter.',
            'task_instructions' => 'Take notes while you read.',
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 35,
            'notes' => 'Original block.',
        ]);

        $response = $this->actingAs($studentUser)
            ->put(route('student.schedules.update', $scheduleTemplate), [
                'name' => 'Tuesday Plan',
                'weekday' => ScheduleWeekday::Tuesday->value,
                'is_active' => false,
                'notes' => 'Updated note.',
                'entries' => [
                    [
                        'task_title' => 'Writing Sprint',
                        'task_summary' => 'Draft the short response.',
                        'task_instructions' => 'Write until the timer ends.',
                        'start_time' => '10:00',
                        'duration_minutes' => 25,
                        'notes' => 'Writing block.',
                    ],
                    [
                        'task_title' => 'Reading Review',
                        'task_summary' => 'Read the follow-up chapter.',
                        'task_instructions' => 'Capture the main points.',
                        'start_time' => '10:30',
                        'duration_minutes' => 35,
                        'notes' => 'Reading follow-up.',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('student.schedules.index', absolute: false))
            ->assertSessionHas('success', 'Расписание Tuesday Plan обновлено.');

        $scheduleTemplate->refresh();
        $scheduleTemplate->load('entries');

        $this->assertSame('Tuesday Plan', $scheduleTemplate->name);
        $this->assertSame(ScheduleWeekday::Tuesday, $scheduleTemplate->weekday);
        $this->assertFalse($scheduleTemplate->is_active);
        $this->assertSame('Updated note.', $scheduleTemplate->notes);
        $this->assertCount(2, $scheduleTemplate->entries);
        $this->assertSame(
            ['Writing Sprint', 'Reading Review'],
            $scheduleTemplate->entries->pluck('task_title')->all(),
        );
    }

    public function test_student_can_not_open_or_update_another_students_schedule(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_student',
        ]);

        $otherStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_other_student',
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

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $otherStudent->id,
            'name' => 'Private Plan',
            'weekday' => ScheduleWeekday::Monday,
            'is_active' => true,
            'notes' => null,
            'created_by_user_id' => $otherStudentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => null,
            'task_title' => 'Reading Review',
            'task_summary' => 'Read the selected chapter.',
            'task_instructions' => 'Take notes while you read.',
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 35,
            'notes' => 'Private block.',
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.schedules.edit', $scheduleTemplate))
            ->assertNotFound();

        $this->actingAs($studentUser)
            ->put(route('student.schedules.update', $scheduleTemplate), [
                'name' => 'Stolen Plan',
                'weekday' => ScheduleWeekday::Tuesday->value,
                'is_active' => true,
                'notes' => null,
                'entries' => [
                    [
                        'task_title' => 'Reading Review',
                        'task_summary' => 'Read the selected chapter.',
                        'task_instructions' => 'Take notes while you read.',
                        'start_time' => '09:00',
                        'duration_minutes' => 35,
                        'notes' => null,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_student_schedule_blocks_must_stay_in_order_and_not_overlap(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_student',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.schedules.store'), [
                'name' => 'Monday Plan',
                'weekday' => ScheduleWeekday::Monday->value,
                'is_active' => true,
                'notes' => null,
                'entries' => [
                    [
                        'task_title' => 'Reading Review',
                        'task_summary' => 'Read the selected chapter.',
                        'task_instructions' => 'Take notes while you read.',
                        'start_time' => '09:30',
                        'duration_minutes' => 35,
                        'notes' => null,
                    ],
                    [
                        'task_title' => 'Writing Sprint',
                        'task_summary' => 'Draft the response.',
                        'task_instructions' => 'Work until the timer ends.',
                        'start_time' => '09:20',
                        'duration_minutes' => 25,
                        'notes' => null,
                    ],
                ],
            ])
            ->assertInvalid(['entries.1.start_time']);
    }

    public function test_student_can_view_their_schedule_index_and_edit_screen(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'schedule_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Plan',
            'weekday' => ScheduleWeekday::Monday,
            'is_active' => true,
            'notes' => 'Visible to the student.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => null,
            'task_title' => 'Reading Review',
            'task_summary' => 'Read the selected chapter.',
            'task_instructions' => 'Take notes while you read.',
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 35,
            'notes' => 'Reading block.',
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.schedules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Schedules/Index')
                ->has('scheduleTemplates', 1)
                ->where('scheduleTemplates.0.name', 'Monday Plan')
                ->where('scheduleTemplates.0.weekday.label', ScheduleWeekday::Monday->label())
                ->where('scheduleTemplates.0.entries.0.task.title', 'Reading Review')
            );

        $this->actingAs($studentUser)
            ->get(route('student.schedules.edit', $scheduleTemplate))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Schedules/Edit')
                ->where('scheduleTemplate.name', 'Monday Plan')
                ->where('scheduleTemplate.entries.0.task_title', 'Reading Review')
            );
    }
}
