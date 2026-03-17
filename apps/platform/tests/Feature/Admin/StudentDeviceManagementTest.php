<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\DeviceActivityEvent;
use App\Models\DeviceCommand;
use App\Models\DeviceCommandResult;
use App\Models\DeviceHeartbeat;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\StudentMonitorCapture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_can_view_companion_debug_for_one_student(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_companion_debug',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_companion_debug',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Companion Debug Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'debug-device',
            'label' => 'Debug Desk',
            'hostname' => 'debug-host',
            'platform' => 'windows',
            'app_version' => '0.2.0',
            'last_seen_at' => now(),
            'last_seen_ip' => '192.168.11.240',
            'last_ipv4' => '192.168.11.240',
            'last_mac_address' => 'AA-BB-CC-DD-EE-FF',
            'last_gateway_ipv4' => '192.168.11.228',
            'network_adapter_name' => 'Ethernet',
            'internet_access_mode' => 'allow_all',
            'last_network_state' => ['mode' => 'allow_all'],
            'meta' => ['build' => 'debug'],
        ]);

        DeviceHeartbeat::create([
            'student_device_id' => $device->id,
            'received_at' => now(),
            'ip_address' => '192.168.11.240',
            'payload' => ['hostname' => 'debug-host'],
        ]);

        DeviceActivityEvent::create([
            'student_device_id' => $device->id,
            'event_type' => 'focused_app',
            'app_name' => 'code.exe',
            'window_title' => 'Visual Studio Code',
            'browser_domain' => null,
            'payload' => ['source' => 'foreground'],
            'observed_at' => now(),
        ]);

        $command = DeviceCommand::create([
            'student_device_id' => $device->id,
            'requested_by_user_id' => $admin->id,
            'command_type' => 'request_screenshot',
            'status' => 'completed',
            'payload' => ['reason' => 'debug'],
            'requested_at' => now(),
            'completed_at' => now(),
        ]);

        DeviceCommandResult::create([
            'device_command_id' => $command->id,
            'student_device_id' => $device->id,
            'status' => 'ok',
            'payload' => ['stored' => true],
            'received_at' => now(),
        ]);

        Storage::disk('public')->put('student-monitor-captures/debug/screen.png', 'debug-image');

        StudentMonitorCapture::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'capture_kind' => 'screen',
            'disk' => 'public',
            'path' => 'student-monitor-captures/debug/screen.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1234,
            'captured_at' => now(),
            'uploaded_at' => now(),
            'task_title_snapshot' => 'Coding',
            'app_name_snapshot' => 'code.exe',
            'window_title_snapshot' => 'Visual Studio Code',
            'browser_domain_snapshot' => null,
            'source_label' => 'AIR Companion',
            'source_version' => '0.2.0',
            'meta' => ['quality' => 'debug'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.devices.debug', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Students/CompanionDebug')
                ->where('student.id', $student->id)
                ->where('devices.0.label', 'Debug Desk')
                ->where('devices.0.heartbeats.0.ip_address', '192.168.11.240')
                ->where('devices.0.activity_events.0.app_name', 'code.exe')
                ->where('devices.0.commands.0.command_type', 'request_screenshot')
                ->where('devices.0.commands.0.results.0.status', 'ok')
                ->where('devices.0.captures.0.capture_kind', 'screen')
                ->where('devices.0.internet_access_mode', 'allow_all')
            );
    }

    public function test_admin_can_change_device_internet_access_mode(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_update_device_internet',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_update_device_internet',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Internet Device Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'internet-device',
            'label' => 'Internet Desk',
            'platform' => 'windows',
            'internet_access_mode' => 'allow_all',
        ]);

        config()->set('services.network_control.enabled', true);
        config()->set('services.network_control.local_gateway_enabled', false);

        $this->actingAs($admin)
            ->patch(route('admin.students.devices.internet.update', [$student, $device]), [
                'internet_access_mode' => 'block_all',
            ])
            ->assertRedirect(route('admin.students.devices.debug', $student, absolute: false))
            ->assertSessionHas('success', 'Internet blocked for Internet Desk.');

        $this->assertDatabaseHas('student_devices', [
            'id' => $device->id,
            'internet_access_mode' => 'block_all',
        ]);
    }

    public function test_admin_can_open_camera_capture_from_companion_debug(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_camera_capture_debug',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_camera_capture_debug',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Camera Capture Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'camera-device',
            'label' => 'Camera Desk',
            'platform' => 'windows',
        ]);

        Storage::disk('public')->put('student-monitor-captures/debug/camera.png', 'camera-image');

        $capture = StudentMonitorCapture::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'capture_kind' => 'camera',
            'disk' => 'public',
            'path' => 'student-monitor-captures/debug/camera.png',
            'mime_type' => 'image/png',
            'size_bytes' => 2345,
            'captured_at' => now(),
            'uploaded_at' => now(),
            'task_title_snapshot' => 'Reading',
            'app_name_snapshot' => 'camera.exe',
            'window_title_snapshot' => 'Camera',
            'browser_domain_snapshot' => null,
            'source_label' => 'AIR Companion',
            'source_version' => '0.2.0',
            'meta' => ['quality' => 'debug'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.student-monitor-captures.show', $capture))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }
}
