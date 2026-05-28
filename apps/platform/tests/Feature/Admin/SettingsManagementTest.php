<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentConsequenceProfile;
use App\Models\StudentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(string $username, string $displayName): Student
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => $username,
        ]);

        return Student::create([
            'user_id' => $studentUser->id,
            'display_name' => $displayName,
            'status' => 'active',
            'notes' => null,
        ]);
    }

    public function test_admin_can_view_student_settings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_settings',
        ]);
        $student = $this->createStudent('student_settings', 'Student Settings');

        StudentSetting::create([
            'student_id' => $student->id,
            'screen_capture_interval_seconds' => 45,
            'camera_capture_interval_seconds' => 75,
        ]);

        StudentConsequenceProfile::create([
            'student_id' => $student->id,
            'default_push_up_count' => 8,
            'current_push_up_count' => 12,
            'increment_push_up_count_per_violation' => false,
            'rest_duration_seconds' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Index')
                ->has('students', 1)
                ->where('students.0.username', 'student_settings')
                ->where('students.0.settings.screen_capture_interval_seconds', 45)
                ->where('students.0.settings.camera_capture_interval_seconds', 75)
                ->where('students.0.consequence_profile.current_push_up_count', 12)
                ->where('students.0.consequence_profile.increment_push_up_count_per_violation', false)
            );
    }

    public function test_admin_can_update_student_settings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_settings_update',
        ]);
        $student = $this->createStudent('student_settings_update', 'Student Settings Update');

        $this->actingAs($admin)
            ->patch(route('admin.settings.students.update', $student), [
                'increment_push_up_count_per_violation' => false,
                'screen_capture_interval_seconds' => 60,
                'camera_capture_interval_seconds' => 90,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'screen_capture_interval_seconds' => 60,
            'camera_capture_interval_seconds' => 90,
        ]);
        $this->assertDatabaseHas('student_consequence_profiles', [
            'student_id' => $student->id,
            'current_push_up_count' => 10,
            'increment_push_up_count_per_violation' => false,
        ]);
    }

    public function test_students_cannot_manage_admin_settings(): void
    {
        $student = $this->createStudent('student_settings_forbidden', 'Student Settings Forbidden');
        $studentUser = $student->user;

        $this->actingAs($studentUser)
            ->get(route('admin.settings.index'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($studentUser)
            ->patch(route('admin.settings.students.update', $student), [
                'increment_push_up_count_per_violation' => false,
                'screen_capture_interval_seconds' => 60,
                'camera_capture_interval_seconds' => 90,
            ])
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
