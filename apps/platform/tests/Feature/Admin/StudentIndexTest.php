<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_student_index(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_console',
        ]);

        $alphaUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'alpha_student',
            'name' => 'Alpha Student User',
        ]);
        $betaUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'beta_student',
            'name' => 'Beta Student User',
        ]);

        Student::create([
            'user_id' => $betaUser->id,
            'display_name' => 'Beta Student',
            'status' => 'paused',
            'notes' => 'Needs follow-up',
        ]);
        Student::create([
            'user_id' => $alphaUser->id,
            'display_name' => 'Alpha Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Index')
                ->has('students', 2)
                ->where('students.0.display_name', 'Alpha Student')
                ->where('students.0.user.username', 'alpha_student')
                ->where('students.1.display_name', 'Beta Student')
                ->where('students.1.user.username', 'beta_student')
            );
    }

    public function test_students_are_redirected_away_from_the_student_index(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_portal',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Portal Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.students.index'))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
