<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCompanionEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_open_companion_enrollment_page(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.companion.enroll'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Companion/Enroll')
                ->where('installer_download_url', route('companion.installer.download'))
                ->where('browser_extension_download_url', route('companion.browser-extension.download'))
                ->where('bootstrap_script_url', route('student.companion.enroll.bootstrap'))
                ->where('browser_extension_setup.platform_url', url('/'))
                ->whereType('browser_extension_setup.device_token', 'string')
            );

        $this->assertDatabaseHas('student_devices', [
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'platform' => 'chrome_extension',
        ]);
    }

    public function test_student_can_download_companion_enrollment_script(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.companion.enroll.bootstrap'))
            ->assertOk()
            ->assertDownload('air-companion-enroll.ps1');

        $this->assertDatabaseHas('device_enrollment_tokens', [
            'student_id' => $student->id,
        ]);
    }
}
