<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentDeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_student_devices_and_queue_commands(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_devices',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_devices',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Device Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'device-one',
            'label' => 'Desk PC',
            'hostname' => 'desk-pc',
            'platform' => 'windows',
            'app_version' => '0.1.0',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.devices.index', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/Devices')
                ->where('student.id', $student->id)
                ->has('devices', 1)
                ->where('devices.0.label', 'Desk PC')
            );

        $this->actingAs($admin)
            ->post(route('admin.students.devices.command', [$student, $device]), [
                'command_type' => 'request_screenshot',
            ])
            ->assertRedirect(route('admin.students.devices.index', $student, absolute: false))
            ->assertSessionHas('success', 'Command queued for Desk PC.');

        $this->assertDatabaseHas('device_commands', [
            'student_device_id' => $device->id,
            'command_type' => 'request_screenshot',
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_rename_and_revoke_student_device(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_revoke_devices',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_revoke_devices',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Device Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'device-one',
            'label' => 'Desk PC',
            'platform' => 'windows',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.students.devices.update', [$student, $device]), [
                'label' => 'Lab Station',
            ])
            ->assertRedirect(route('admin.students.devices.index', $student, absolute: false))
            ->assertSessionHas('success', 'Device Lab Station updated.');

        $device->refresh();
        $this->assertSame('Lab Station', $device->label);

        $this->actingAs($admin)
            ->patch(route('admin.students.devices.revoke', [$student, $device]))
            ->assertRedirect(route('admin.students.devices.index', $student, absolute: false))
            ->assertSessionHas('success', 'Device Lab Station revoked.');

        $this->assertNotNull($device->fresh()->revoked_at);
    }
}
