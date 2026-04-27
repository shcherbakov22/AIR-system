<?php

namespace Tests\Feature\Admin;

use App\Enums\ScheduleWeekday;
use App\Enums\UserRole;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_an_empty_schedule_list(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedules',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.schedule-templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ScheduleTemplates/Index')
                ->has('scheduleTemplates', 0)
            );
    }

    public function test_admin_can_view_the_create_schedule_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedules',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedules',
            'status' => 'active',
            'notes' => null,
        ]);

        TaskTemplate::create([
            'title' => 'Reading Block',
            'summary' => 'Read and summarize.',
            'instructions' => 'Read the material and summarize it.',
            'default_duration_minutes' => 40,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.schedule-templates.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ScheduleTemplates/Create')
                ->has('students', 1)
                ->has('taskTemplates', 1)
                ->has('weekdays', 7)
            );
    }

    public function test_admin_can_create_a_schedule_template_with_an_initial_entry(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedules',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedules',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Block',
            'summary' => 'Read and summarize.',
            'instructions' => 'Read the material and summarize it.',
            'default_duration_minutes' => 40,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.schedule-templates.store'), [
            'student_id' => $student->id,
            'weekday' => ScheduleWeekday::Monday->value,
            'notes' => 'Core literacy block.',
            'entries' => [
                [
                    'task_template_id' => $taskTemplate->id,
                    'notes' => 'Bring the chapter notebook.',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('admin.schedule-templates.index', absolute: false))
            ->assertSessionHas('success', 'Schedule saved for Student Schedules.');

        $this->assertDatabaseHas('schedule_templates', [
            'student_id' => $student->id,
            'name' => ScheduleTemplate::DEFAULT_NAME,
            'weekday' => ScheduleWeekday::Monday->value,
            'notes' => 'Core literacy block.',
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate = ScheduleTemplate::query()->firstOrFail();

        $this->assertDatabaseHas('schedule_entries', [
            'schedule_template_id' => $scheduleTemplate->id,
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'duration_minutes' => 40,
            'notes' => 'Bring the chapter notebook.',
        ]);

        $this->assertSame('09:00', substr((string) $scheduleTemplate->entries()->firstOrFail()->start_time, 0, 5));
    }

    public function test_admin_can_view_the_edit_schedule_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedules',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedules',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Block',
            'summary' => 'Read and summarize.',
            'instructions' => 'Read the material and summarize it.',
            'default_duration_minutes' => 40,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Reading',
            'weekday' => ScheduleWeekday::Monday,
            'notes' => 'Core literacy block.',
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'start_time' => '09:15',
            'duration_minutes' => 40,
            'notes' => 'Bring the chapter notebook.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.schedule-templates.edit', $scheduleTemplate))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ScheduleTemplates/Edit')
                ->where('scheduleTemplate.id', $scheduleTemplate->id)
                ->where('scheduleTemplate.entries.0.task_template_id', $taskTemplate->id)
                ->has('students', 1)
                ->has('taskTemplates', 1)
                ->has('weekdays', 7)
            );
    }

    public function test_admin_can_update_a_schedule_template_and_its_single_entry(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedules',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedules',
            'status' => 'active',
            'notes' => null,
        ]);

        $secondStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedules_two',
        ]);

        $secondStudent = Student::create([
            'user_id' => $secondStudentUser->id,
            'display_name' => 'Student Schedules Two',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Block',
            'summary' => 'Read and summarize.',
            'instructions' => 'Read the material and summarize it.',
            'default_duration_minutes' => 40,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $secondTaskTemplate = TaskTemplate::create([
            'title' => 'Writing Block',
            'summary' => 'Write and revise.',
            'instructions' => 'Write the response and revise it.',
            'default_duration_minutes' => 50,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Reading',
            'weekday' => ScheduleWeekday::Monday,
            'notes' => 'Core literacy block.',
            'created_by_user_id' => $admin->id,
        ]);

        $entry = $scheduleTemplate->entries()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'start_time' => '09:15',
            'duration_minutes' => 40,
            'notes' => 'Bring the chapter notebook.',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.schedule-templates.update', $scheduleTemplate), [
            'student_id' => $secondStudent->id,
            'weekday' => ScheduleWeekday::Wednesday->value,
            'notes' => 'Midweek writing focus.',
            'entries' => [
                [
                    'task_template_id' => $secondTaskTemplate->id,
                    'notes' => 'Start with the outline.',
                ],
            ],
        ]);

        $response
            ->assertRedirect(route('admin.schedule-templates.index', absolute: false))
            ->assertSessionHas('success', 'Schedule saved for Student Schedules Two.');

        $this->assertDatabaseHas('schedule_templates', [
            'id' => $scheduleTemplate->id,
            'student_id' => $secondStudent->id,
            'name' => ScheduleTemplate::DEFAULT_NAME,
            'weekday' => ScheduleWeekday::Wednesday->value,
            'notes' => 'Midweek writing focus.',
            'created_by_user_id' => $admin->id,
        ]);

        $this->assertDatabaseMissing('schedule_entries', [
            'id' => $entry->id,
        ]);

        $this->assertDatabaseHas('schedule_entries', [
            'schedule_template_id' => $scheduleTemplate->id,
            'task_template_id' => $secondTaskTemplate->id,
            'position' => 1,
            'duration_minutes' => 50,
            'notes' => 'Start with the outline.',
        ]);

        $replacementEntry = $scheduleTemplate->entries()->firstOrFail();

        $this->assertSame('09:00', substr((string) $replacementEntry->start_time, 0, 5));
    }

    public function test_admin_can_keep_the_current_task_template_when_updating_a_schedule(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedules',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedules',
            'status' => 'active',
            'notes' => null,
        ]);

        $currentTaskTemplate = TaskTemplate::create([
            'title' => 'Reading Block',
            'summary' => 'Legacy block.',
            'instructions' => 'Keep the existing block intact.',
            'default_duration_minutes' => 25,
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Archived Monday Reading',
            'weekday' => ScheduleWeekday::Monday,
            'notes' => 'Legacy schedule.',
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $currentTaskTemplate->id,
            'position' => 1,
            'start_time' => '08:30',
            'duration_minutes' => 25,
            'notes' => 'Legacy note.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.schedule-templates.edit', $scheduleTemplate))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ScheduleTemplates/Edit')
                ->has('taskTemplates', 1)
                ->where('taskTemplates.0.id', $currentTaskTemplate->id)
            );

        $this->actingAs($admin)
            ->put(route('admin.schedule-templates.update', $scheduleTemplate), [
                'student_id' => $student->id,
                'weekday' => ScheduleWeekday::Monday->value,
                'notes' => 'Legacy schedule updated.',
                'entries' => [
                    [
                        'task_template_id' => $currentTaskTemplate->id,
                        'notes' => 'Still tied to the current task.',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.schedule-templates.index', absolute: false))
            ->assertSessionHas('success', 'Schedule saved for Student Schedules.');

        $scheduleTemplate->refresh();
        $entry = $scheduleTemplate->entries()->firstOrFail();

        $this->assertSame('Legacy schedule updated.', $scheduleTemplate->notes);
        $this->assertSame('Still tied to the current task.', $entry->notes);
        $this->assertSame('09:00', substr((string) $entry->start_time, 0, 5));
    }

    public function test_admin_can_view_existing_schedule_templates(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedules',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedules',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Block',
            'summary' => 'Read and summarize.',
            'instructions' => 'Read the material and summarize it.',
            'default_duration_minutes' => 40,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Reading',
            'weekday' => ScheduleWeekday::Monday,
            'notes' => 'Core literacy block.',
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'start_time' => '09:15',
            'duration_minutes' => 40,
            'notes' => 'Bring the chapter notebook.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.schedule-templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ScheduleTemplates/Index')
                ->has('scheduleTemplates', 1)
                ->where('scheduleTemplates.0.weekday.label', ScheduleWeekday::Monday->label())
                ->where('scheduleTemplates.0.entries.0.task_template.title', 'Reading Block')
                ->where('scheduleTemplates.0.entries.0.start_time', '09:15')
            );
    }

    public function test_admin_can_delete_a_schedule_template(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedules',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedules',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedules',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Block',
            'summary' => 'Read and summarize.',
            'instructions' => 'Read the material and summarize it.',
            'default_duration_minutes' => 40,
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Reading',
            'weekday' => ScheduleWeekday::Monday,
            'notes' => 'Core literacy block.',
            'created_by_user_id' => $admin->id,
        ]);

        $entry = $scheduleTemplate->entries()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'start_time' => '09:15',
            'duration_minutes' => 40,
            'notes' => 'Bring the chapter notebook.',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.schedule-templates.destroy', $scheduleTemplate))
            ->assertRedirect(route('admin.schedule-templates.index', absolute: false))
            ->assertSessionHas('success', 'Schedule deleted for Student Schedules.');

        $this->assertDatabaseMissing('schedule_templates', [
            'id' => $scheduleTemplate->id,
        ]);

        $this->assertDatabaseMissing('schedule_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_students_are_redirected_away_from_schedule_routes(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedules',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.schedule-templates.index'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('admin.schedule-templates.create'))
            ->assertRedirect(route('dashboard', absolute: false));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedules_blocked',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Schedules',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading Block',
            'summary' => 'Read and summarize.',
            'instructions' => 'Read the material and summarize it.',
            'default_duration_minutes' => 40,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Monday Reading',
            'weekday' => ScheduleWeekday::Monday,
            'notes' => 'Core literacy block.',
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => $taskTemplate->id,
            'position' => 1,
            'start_time' => '09:15',
            'duration_minutes' => 40,
            'notes' => 'Bring the chapter notebook.',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.schedule-templates.edit', $scheduleTemplate))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
