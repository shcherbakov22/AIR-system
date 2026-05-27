<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_create_student_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_creator',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Students/Create'));
    }

    public function test_admin_can_create_a_student_account_and_profile(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_creator',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.students.store'), [
            'username' => 'new_student',
            'name' => 'New Student User',
            'display_name' => 'New Student',
            'password' => 'StudentPass123!',
            'can_manage_own_schedule' => true,
            'can_use_ad_hoc_timer' => false,
            'look_away_event_threshold' => 4,
            'preferred_timezone' => 'Africa/Cairo',
            'default_push_up_count' => 12,
            'rest_duration_seconds' => 90,
        ]);

        $response
            ->assertRedirect(route('admin.students.index', absolute: false))
            ->assertSessionHas('success', 'Student New Student has been created.');

        $this->assertDatabaseHas('users', [
            'username' => 'new_student',
            'name' => 'New Student User',
            'email' => null,
            'role' => UserRole::Student->value,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('students', [
            'display_name' => 'New Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $studentUser = User::query()->where('username', 'new_student')->firstOrFail();
        $student = Student::query()->where('user_id', $studentUser->id)->firstOrFail();

        $this->assertTrue(Hash::check('StudentPass123!', $studentUser->password));
        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'can_manage_own_schedule' => true,
            'can_use_ad_hoc_timer' => false,
            'look_away_event_threshold' => 4,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
            'preferred_timezone' => 'Africa/Cairo',
        ]);
        $this->assertDatabaseHas('student_consequence_profiles', [
            'student_id' => $student->id,
            'default_push_up_count' => 12,
            'current_push_up_count' => 10,
            'increment_push_up_count_per_violation' => true,
            'rest_duration_seconds' => 90,
            'notes' => null,
        ]);
    }

    public function test_students_are_redirected_away_from_the_create_student_screen(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_blocked',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.students.create'))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
