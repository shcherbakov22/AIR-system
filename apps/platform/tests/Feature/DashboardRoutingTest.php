<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\ScheduleWeekday;
use App\Models\RuleDefinition;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_are_sent_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_dashboard',
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('serverNow')
                ->has('activeTaskSessions', 0)
            );
    }

    public function test_admin_dashboard_shows_active_student_task_sessions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_activity',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_activity',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Activity Student',
            'status' => 'active',
            'notes' => null,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 40,
            'started_at' => now()->subMinutes(12),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('activeTaskSessions', 1)
                ->where('activeTaskSessions.0.student.display_name', 'Activity Student')
                ->where('activeTaskSessions.0.task_title', 'Reading')
                ->where('activeTaskSessions.0.planned_duration_minutes', 40)
            );
    }

    public function test_student_users_are_sent_to_the_student_home(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_home',
            'name' => 'Student Home User',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Home',
            'status' => 'active',
            'notes' => 'Ready for tasks',
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $studentUser->student->id,
            'name' => 'Monday Focus Block',
            'weekday' => ScheduleWeekday::Monday,
            'is_active' => true,
            'notes' => 'Core morning work.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => null,
            'task_title' => 'Math Review',
            'task_summary' => 'Quick review task.',
            'task_instructions' => 'Complete the review sheet.',
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'notes' => 'Start with the review sheet.',
        ]);

        $inactiveScheduleTemplate = ScheduleTemplate::create([
            'student_id' => $studentUser->student->id,
            'name' => 'Friday Archive',
            'weekday' => ScheduleWeekday::Friday,
            'is_active' => false,
            'notes' => 'This should stay out of the student portal.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $inactiveScheduleTemplate->entries()->create([
            'task_template_id' => null,
            'task_title' => 'Math Review',
            'task_summary' => 'Quick review task.',
            'task_instructions' => 'Complete the review sheet.',
            'position' => 1,
            'start_time' => '14:00',
            'duration_minutes' => 30,
            'notes' => 'Inactive block note.',
        ]);

        $this->actingAs($studentUser)
            ->get('/dashboard')
            ->assertRedirect(route('student.home', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('student.display_name', 'Student Home')
                ->where('student.status', 'active')
                ->where('student.notes', 'Ready for tasks')
                                ->where('violationSummary.open_violations', 0)
                ->missing('taskAssignments')
                ->where('activeTaskSession', null)
                ->missing('recentTaskSessions')
                ->has('weeklyScheduleTemplates', 2)
                ->where('weeklyScheduleTemplates.0.name', 'Monday Focus Block')
                ->where('weeklyScheduleTemplates.0.weekday.label', ScheduleWeekday::Monday->label())
                ->where('weeklyScheduleTemplates.0.entries.0.task.title', 'Math Review')
                ->where('weeklyScheduleTemplates.0.entries.0.start_time', '09:00')
            );
    }

    public function test_student_home_reflects_open_violations_from_admin_logged_violations(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_home_penalties',
            'name' => 'Student Home Penalties',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Penalties',
            'status' => 'active',
            'notes' => null,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_home_penalties',
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on task',
            'description' => 'Student must stay on the active task.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.violations.store'), [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'occurred_at' => '2026-03-08T12:05',
                        'notes' => 'Left the task without permission.',
        ])->assertRedirect(route('admin.violations.index', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('student.display_name', 'Student Penalties')
                                ->where('violationSummary.open_violations', 1)
            );
    }
}
