<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_edit_student_screen(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_editor',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'editable_student',
            'name' => 'Editable Student User',
            'email' => 'editable.student@example.com',
            'is_active' => false,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Editable Student',
            'status' => 'paused',
            'notes' => 'Needs review',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.edit', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Edit')
                ->where('student.id', $student->id)
                ->where('student.display_name', 'Editable Student')
                ->where('student.user.username', 'editable_student')
                ->where('student.user.last_login_at', null)
                ->where('student.settings.can_manage_own_schedule', true)
                ->where('student.settings.look_away_event_threshold', 3)
                ->where('student.consequence_profile.default_push_up_count', 0)
                ->where('student.consequence_profile.current_push_up_count', 10)
                ->where('student.consequence_profile.increment_push_up_count_per_violation', true)
            );
    }

    public function test_admin_can_update_a_student_account_and_profile(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_editor',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'editable_student',
            'name' => 'Editable Student User',
            'email' => 'editable.student@example.com',
            'is_active' => false,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Editable Student',
            'status' => 'paused',
            'notes' => 'Needs review',
        ]);

        $existingPasswordHash = $studentUser->password;

        $response = $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'username' => 'edited_student',
            'name' => 'Edited Student User',
            'display_name' => 'Edited Student',
            'can_manage_own_schedule' => false,
            'can_use_ad_hoc_timer' => false,
            'look_away_event_threshold' => 5,
            'preferred_timezone' => 'Africa/Cairo',
            'default_push_up_count' => 8,
            'increment_push_up_count_per_violation' => false,
            'rest_duration_seconds' => 120,
        ]);

        $response
            ->assertRedirect(route('admin.students.index', absolute: false))
            ->assertSessionHas('success', 'Student Edited Student has been updated.');

        $studentUser->refresh();
        $student->refresh();

        $this->assertSame('edited_student', $studentUser->username);
        $this->assertSame('Edited Student User', $studentUser->name);
        $this->assertNull($studentUser->email);
        $this->assertFalse($studentUser->is_active);
        $this->assertSame($existingPasswordHash, $studentUser->password);

        $this->assertSame('Edited Student', $student->display_name);
        $this->assertSame('paused', $student->status);
        $this->assertSame('Needs review', $student->notes);
        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'can_manage_own_schedule' => false,
            'can_use_ad_hoc_timer' => false,
            'look_away_event_threshold' => 5,
            'preferred_timezone' => 'Africa/Cairo',
        ]);
        $this->assertDatabaseHas('student_consequence_profiles', [
            'student_id' => $student->id,
            'default_push_up_count' => 8,
            'current_push_up_count' => 10,
            'increment_push_up_count_per_violation' => false,
            'rest_duration_seconds' => 120,
            'notes' => null,
        ]);
    }

    public function test_admin_can_update_a_student_password(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_password_editor',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'password_student',
            'password' => Hash::make('before'),
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Password Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.students.password.update', $student), [
                'password' => '0',
                'password_confirmation' => '0',
            ])
            ->assertRedirect(route('admin.students.edit', $student, absolute: false))
            ->assertSessionHas('success', 'Password for Password Student has been updated.');

        $studentUser->refresh();

        $this->assertTrue(Hash::check('0', $studentUser->password));
    }

    public function test_admin_can_delete_a_student_account(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_student_destroyer',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'delete_me_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Delete Me Student',
            'status' => 'active',
            'notes' => 'Remove this student',
        ]);

        $student->taskSessions()->create([
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(2),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('admin.students.index', absolute: false))
            ->assertSessionHas('success', 'Student Delete Me Student has been deleted.');

        $this->assertDatabaseMissing('users', [
            'id' => $studentUser->id,
        ]);
        $this->assertDatabaseMissing('students', [
            'id' => $student->id,
        ]);
        $this->assertDatabaseCount('task_sessions', 0);
    }

    public function test_students_are_redirected_away_from_the_edit_student_screen(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_blocked',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Blocked Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.students.edit', $student))
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_students_are_redirected_away_from_deleting_students(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_delete_blocked',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Delete Blocked Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($studentUser)
            ->delete(route('admin.students.destroy', $student))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
