<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                ->where('student.user.is_active', false)
                ->where('student.settings.can_manage_own_schedule', true)
                ->where('student.consequence_profile.default_push_up_count', 0)
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
            'email' => '',
            'status' => 'active',
            'notes' => 'Updated by admin.',
            'is_active' => true,
            'can_manage_own_schedule' => false,
            'can_use_ad_hoc_timer' => false,
            'preferred_timezone' => 'Africa/Cairo',
            'default_push_up_count' => 8,
            'rest_duration_seconds' => 120,
            'consequence_notes' => 'Updated consequence profile.',
        ]);

        $response
            ->assertRedirect(route('admin.students.index', absolute: false))
            ->assertSessionHas('success', 'Student Edited Student has been updated.');

        $studentUser->refresh();
        $student->refresh();

        $this->assertSame('edited_student', $studentUser->username);
        $this->assertSame('Edited Student User', $studentUser->name);
        $this->assertNull($studentUser->email);
        $this->assertTrue($studentUser->is_active);
        $this->assertSame($existingPasswordHash, $studentUser->password);

        $this->assertSame('Edited Student', $student->display_name);
        $this->assertSame('active', $student->status);
        $this->assertSame('Updated by admin.', $student->notes);
        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'can_manage_own_schedule' => false,
            'can_use_ad_hoc_timer' => false,
            'preferred_timezone' => 'Africa/Cairo',
        ]);
        $this->assertDatabaseHas('student_consequence_profiles', [
            'student_id' => $student->id,
            'default_push_up_count' => 8,
            'rest_duration_seconds' => 120,
            'notes' => 'Updated consequence profile.',
        ]);
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
}
