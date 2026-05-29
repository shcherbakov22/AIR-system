<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentDevice;
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
                ->where('repair_script_url', route('student.companion.enroll.repair-permissions'))
                ->where('browser_extension_download_url', route('companion.browser-extension.download'))
                ->where('browser_extension_enterprise_script_url', route('student.companion.enroll.browser-extension-enterprise-install'))
                ->where('bootstrap_script_url', route('student.companion.enroll.bootstrap'))
                ->where('browser_extension_setup.platform_url', url('/'))
                ->where('browser_extension_setup.device_token', '')
                ->where('browser_extension_configure_url', route('student.companion.enroll.browser-extension-token'))
            );

        $this->assertDatabaseHas('student_devices', [
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'platform' => 'chrome_extension',
        ]);
    }

    public function test_browser_extension_setup_token_is_only_rotated_by_explicit_configure_request(): void
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
                ->where('browser_extension_setup.device_token', '')
            );

        $this->assertDatabaseHas('student_devices', [
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'platform' => 'chrome_extension',
            'token_hash' => null,
        ]);

        $tokenResponse = $this->actingAs($studentUser)
            ->postJson(route('student.companion.enroll.browser-extension-token'))
            ->assertOk()
            ->assertJsonPath('platform_url', url('/'))
            ->assertJsonStructure(['device_token']);

        $token = $tokenResponse->json('device_token');

        $this->actingAs($studentUser)
            ->get(route('student.companion.enroll'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->whereType('browser_extension_setup.device_token', 'string')
            );

        $this->assertDatabaseHas('student_devices', [
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'platform' => 'chrome_extension',
            'token_hash' => hash('sha256', $token),
        ]);
    }

    public function test_browser_extension_setup_token_is_stable_across_enrollment_page_loads(): void
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

        $firstResponse = $this->actingAs($studentUser)
            ->postJson(route('student.companion.enroll.browser-extension-token'))
            ->assertOk();

        $firstToken = $firstResponse->json('device_token');

        $secondResponse = $this->actingAs($studentUser)
            ->get(route('student.companion.enroll'))
            ->assertOk();

        $secondToken = $secondResponse->viewData('page')['props']['browser_extension_setup']['device_token'];

        $this->assertSame($firstToken, $secondToken);

        $this->assertDatabaseHas('student_devices', [
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'platform' => 'chrome_extension',
            'token_hash' => hash('sha256', $firstToken),
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

    public function test_student_can_download_companion_permission_repair_script(): void
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
            ->get(route('student.companion.enroll.repair-permissions'))
            ->assertOk()
            ->assertDownload('repair-companion-permissions.ps1')
            ->assertSee('AIR Companion permissions repaired and service is running.', false)
            ->assertSee('icacls.exe', false)
            ->assertSee('/inheritance:e', false)
            ->assertSee('*S-1-5-32-545:(OI)(CI)M', false)
            ->assertSee('Register-ScheduledTask', false)
            ->assertSee('New-ScheduledTaskPrincipal', false)
            ->assertDontSee('/inheritance:r', false)
            ->assertDontSee('schtasks.exe', false)
            ->assertSee('LocalSystem', false);
    }

    public function test_student_can_download_browser_extension_enterprise_install_script(): void
    {
        config()->set('services.companion_updates.chrome_enterprise_enrollment_token', 'test-enterprise-token');

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
            ->get(route('student.companion.enroll.browser-extension-enterprise-install'))
            ->assertOk()
            ->assertDownload('install-browser-extension-enterprise-policy.ps1')
            ->assertSee('cccijfadcaffnndbpdfdhbncehedgkhb', false)
            ->assertSee(route('companion.browser-extension.update-manifest'), false)
            ->assertSee('ExtensionInstallForcelist', false)
            ->assertSee('HKLM:\Software\Policies\Google\Chrome', false)
            ->assertSee('HKLM:\Software\Policies\Microsoft\Edge', false)
            ->assertSee('3rdparty\extensions', false)
            ->assertSee('platformUrl', false)
            ->assertSee('deviceToken', false)
            ->assertSee('ExtensionSettings', false)
            ->assertSee('force_installed', false)
            ->assertSee('force_pinned', false)
            ->assertSee('CloudManagementEnrollmentToken', false)
            ->assertDontSee('HKLM:\SOFTWARE\Google\Chrome\Enrollment', false)
            ->assertDontSee('HKLM:\Software\WOW6432Node\Google\Enrollment', false)
            ->assertDontSee('Clear-LocalChromeForceInstallPolicy', false)
            ->assertDontSee('Local Chrome force-install policy cleared', false)
            ->assertSee('Chrome and Edge local force-install policies written.', false)
            ->assertSee('Chrome and Edge ExtensionSettings policies written.', false)
            ->assertSee('test-enterprise-token', false)
            ->assertSee('certutil.exe -addstore -f Root', false);

        $this->assertDatabaseHas('student_devices', [
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'platform' => 'chrome_extension',
        ]);
    }

    public function test_browser_extension_enterprise_install_script_reuses_existing_setup_token(): void
    {
        config()->set('services.companion_updates.chrome_enterprise_enrollment_token', 'test-enterprise-token');

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

        $firstToken = $this->actingAs($studentUser)
            ->postJson(route('student.companion.enroll.browser-extension-token'))
            ->assertOk()
            ->json('device_token');

        $this->actingAs($studentUser)
            ->get(route('student.companion.enroll.browser-extension-enterprise-install'))
            ->assertOk()
            ->assertSee($firstToken, false);

        $this->assertDatabaseHas('student_devices', [
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'platform' => 'chrome_extension',
            'token_hash' => hash('sha256', $firstToken),
        ]);
    }

    public function test_browser_extension_enterprise_install_script_reactivates_revoked_extension_device(): void
    {
        config()->set('services.companion_updates.chrome_enterprise_enrollment_token', 'test-enterprise-token');

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

        $token = 'existing-extension-token';
        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'label' => 'Chrome browser extension',
            'hostname' => null,
            'platform' => 'chrome_extension',
            'app_version' => '0.1.0',
            'token_hash' => hash('sha256', $token),
            'meta' => ['setup_token' => $token],
            'revoked_at' => now(),
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.companion.enroll.browser-extension-enterprise-install'))
            ->assertOk()
            ->assertSee($token, false);

        $device = StudentDevice::query()
            ->where('device_key', 'browser-extension:student:'.$student->id)
            ->firstOrFail();

        $this->assertNull($device->revoked_at);
        $this->assertSame(hash('sha256', $token), $device->token_hash);
        $this->assertSame($token, $device->meta['setup_token']);
    }
}
