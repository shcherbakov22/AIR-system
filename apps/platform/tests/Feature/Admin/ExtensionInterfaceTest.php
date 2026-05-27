<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\BrowserAccessRequest;
use App\Models\BrowserPolicyRule;
use App\Models\BrowserVisitLog;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\StudentSetting;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExtensionInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_extension_overview_and_focused_student_data(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_extension',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'extension_student',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Extension Student',
            'status' => 'active',
            'notes' => null,
        ]);
        StudentSetting::create([
            'student_id' => $student->id,
            'can_manage_own_schedule' => true,
            'can_use_ad_hoc_timer' => true,
        ]);
        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'extension-device',
            'label' => 'Desk Extension',
            'platform' => 'chrome_extension',
            'internet_access_mode' => 'whitelist',
            'last_seen_at' => now(),
        ]);
        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'native-companion-device',
            'label' => 'Native Companion',
            'platform' => 'windows',
            'internet_access_mode' => 'whitelist',
            'last_seen_at' => now(),
        ]);
        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension-placeholder',
            'label' => 'Chrome browser extension',
            'platform' => 'chrome_extension',
            'internet_access_mode' => 'whitelist',
            'last_seen_at' => null,
        ]);
        BrowserPolicyRule::create([
            'student_id' => $student->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'docs.python.org',
        ]);
        $accessRequest = BrowserAccessRequest::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'requested_url' => 'https://laravel.com/docs?utm_source=newsletter&lesson=3&fbclid=abc123',
            'host' => 'laravel.com',
            'registrable_domain' => 'laravel.com',
            'reason' => 'Need docs',
            'status' => 'pending',
        ]);
        $visit = BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'mode' => 'whitelist',
            'decision' => 'blocked',
            'url' => 'https://laravel.com/docs?utm_campaign=spring&page=2&gclid=xyz',
            'host' => 'laravel.com',
            'registrable_domain' => 'laravel.com',
            'page_title' => 'Laravel Docs',
            'visited_at' => now(),
        ]);
        $taskTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => null,
            'default_duration_minutes' => 30,
            'requires_internet' => true,
            'created_by_user_id' => $admin->id,
        ]);
        BrowserPolicyRule::create([
            'task_template_id' => $taskTemplate->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'github.com',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.extension.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Extension/Index')
                ->where('focusedStudent.id', $student->id)
                ->where('focusedStudent.browser_accountability.mode', 'whitelist')
                ->where('focusedStudent.browser_accountability.history_clear_url', route('admin.students.browser-history.destroy', $student))
                ->where('focusedStudent.browser_accountability.rules.0.value', 'docs.python.org')
                ->where('focusedStudent.browser_accountability.access_requests.0.registrable_domain', 'laravel.com')
                ->where('focusedStudent.browser_accountability.access_requests.0.display_url', 'laravel.com/docs?lesson=3')
                ->where('focusedStudent.browser_accountability.access_requests.0.destroy_url', route('admin.students.browser-access-requests.destroy', [$student, $accessRequest]))
                ->where('focusedStudent.browser_accountability.current_visit.id', $visit->id)
                ->where('focusedStudent.browser_accountability.current_visit.display_url', 'laravel.com/docs?page=2')
                ->where('focusedStudent.browser_accountability.current_visit.destroy_url', route('admin.students.browser-history.logs.destroy', [$student, $visit]))
                ->where('focusedStudent.browser_accountability.recent_visits.0.decision', 'blocked')
                ->where('focusedStudent.browser_accountability.recent_visits.0.display_url', 'laravel.com/docs?page=2')
                ->where('focusedStudent.browser_accountability.recent_visits.0.destroy_url', route('admin.students.browser-history.logs.destroy', [$student, $visit]))
                ->where('focusedStudent.devices.0.platform', 'chrome_extension')
                ->has('focusedStudent.devices', 1)
                ->where('students.0.device_count', 1)
                ->where('task_allowlists.0.title', 'Coding')
                ->where('task_allowlists.0.domains.0.value', 'github.com')
                ->where('extension_download_url', route('companion.browser-extension.download'))
            );
    }

    public function test_admin_can_reset_a_per_task_browser_allowlist_rule(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_extension_reset_task_allowlist',
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => null,
            'default_duration_minutes' => 30,
            'requires_internet' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $rule = BrowserPolicyRule::create([
            'task_template_id' => $taskTemplate->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'github.com',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'extension_reset_student',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Extension Reset Student',
            'status' => 'active',
            'notes' => null,
        ]);
        $studentRule = BrowserPolicyRule::create([
            'student_id' => $student->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'student-only.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.extension.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Extension/Index')
                ->where('task_allowlists.0.domains.0.destroy_url', route('admin.extension.task-allowlists.destroy', $rule))
            );

        $this->actingAs($admin)
            ->delete(route('admin.extension.task-allowlists.destroy', $rule))
            ->assertRedirect()
            ->assertSessionHas('success', 'Reset github.com for Coding. Students can request approval again.');

        $this->assertDatabaseMissing('browser_policy_rules', [
            'id' => $rule->id,
        ]);
        $this->assertDatabaseHas('browser_policy_rules', [
            'id' => $studentRule->id,
            'value' => 'student-only.test',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.extension.task-allowlists.destroy', $studentRule))
            ->assertNotFound();
    }

    public function test_admin_can_clear_browser_history_for_a_student(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_extension_clear_history',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'extension_clear_history_student',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'History Student',
            'status' => 'active',
            'notes' => null,
        ]);
        $otherStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'extension_other_history_student',
        ]);
        $otherStudent = Student::create([
            'user_id' => $otherStudentUser->id,
            'display_name' => 'Other History Student',
            'status' => 'active',
            'notes' => null,
        ]);
        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'history-device',
            'label' => 'History Device',
            'platform' => 'windows',
        ]);
        $otherDevice = StudentDevice::create([
            'student_id' => $otherStudent->id,
            'device_key' => 'other-history-device',
            'label' => 'Other History Device',
            'platform' => 'windows',
        ]);

        BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'mode' => 'blacklist',
            'decision' => 'allowed',
            'url' => 'https://example.com/one',
            'host' => 'example.com',
            'registrable_domain' => 'example.com',
            'visited_at' => now(),
        ]);
        BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'mode' => 'blacklist',
            'decision' => 'blocked',
            'url' => 'https://blocked.test/two',
            'host' => 'blocked.test',
            'registrable_domain' => 'blocked.test',
            'visited_at' => now(),
        ]);
        BrowserVisitLog::create([
            'student_id' => $otherStudent->id,
            'student_device_id' => $otherDevice->id,
            'mode' => 'blacklist',
            'decision' => 'allowed',
            'url' => 'https://other.test/keep',
            'host' => 'other.test',
            'registrable_domain' => 'other.test',
            'visited_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.students.browser-history.destroy', $student))
            ->assertRedirect()
            ->assertSessionHas('success', 'Cleared 2 browser history entries.');

        $this->assertDatabaseMissing('browser_visit_logs', [
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('browser_visit_logs', [
            'student_id' => $otherStudent->id,
            'host' => 'other.test',
        ]);
    }

    public function test_admin_can_delete_one_browser_history_tile(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_extension_delete_history_tile',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'extension_delete_history_tile_student',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'History Tile Student',
            'status' => 'active',
            'notes' => null,
        ]);
        $otherStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'extension_delete_other_history_tile_student',
        ]);
        $otherStudent = Student::create([
            'user_id' => $otherStudentUser->id,
            'display_name' => 'Other History Tile Student',
            'status' => 'active',
            'notes' => null,
        ]);
        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'history-tile-device',
            'label' => 'History Tile Device',
            'platform' => 'windows',
        ]);
        $otherDevice = StudentDevice::create([
            'student_id' => $otherStudent->id,
            'device_key' => 'other-history-tile-device',
            'label' => 'Other History Tile Device',
            'platform' => 'windows',
        ]);

        $deletedVisit = BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'mode' => 'blacklist',
            'decision' => 'blocked',
            'url' => 'https://blocked.test/delete-me',
            'host' => 'blocked.test',
            'registrable_domain' => 'blocked.test',
            'visited_at' => now(),
        ]);
        BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'mode' => 'blacklist',
            'decision' => 'allowed',
            'url' => 'https://keep.test/one',
            'host' => 'keep.test',
            'registrable_domain' => 'keep.test',
            'visited_at' => now(),
        ]);
        $otherVisit = BrowserVisitLog::create([
            'student_id' => $otherStudent->id,
            'student_device_id' => $otherDevice->id,
            'mode' => 'blacklist',
            'decision' => 'allowed',
            'url' => 'https://other.test/keep',
            'host' => 'other.test',
            'registrable_domain' => 'other.test',
            'visited_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.students.browser-history.logs.destroy', [$student, $deletedVisit]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Removed blocked.test from browser history.');

        $this->assertDatabaseMissing('browser_visit_logs', [
            'id' => $deletedVisit->id,
        ]);
        $this->assertDatabaseHas('browser_visit_logs', [
            'student_id' => $student->id,
            'host' => 'keep.test',
        ]);
        $this->assertDatabaseHas('browser_visit_logs', [
            'id' => $otherVisit->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.students.browser-history.logs.destroy', [$student, $otherVisit]))
            ->assertNotFound();
    }

    public function test_admin_can_delete_request_tile_and_pending_request_is_denied_first(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_extension_delete_request_tile',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'extension_delete_request_tile_student',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Request Tile Student',
            'status' => 'active',
            'notes' => null,
        ]);
        $otherStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'extension_delete_other_request_tile_student',
        ]);
        $otherStudent = Student::create([
            'user_id' => $otherStudentUser->id,
            'display_name' => 'Other Request Tile Student',
            'status' => 'active',
            'notes' => null,
        ]);
        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'request-tile-device',
            'label' => 'Request Tile Device',
            'platform' => 'windows',
        ]);
        $otherDevice = StudentDevice::create([
            'student_id' => $otherStudent->id,
            'device_key' => 'other-request-tile-device',
            'label' => 'Other Request Tile Device',
            'platform' => 'windows',
        ]);

        $request = BrowserAccessRequest::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'requested_url' => 'https://blocked.test/request',
            'host' => 'blocked.test',
            'registrable_domain' => 'blocked.test',
            'reason' => 'Need this site.',
            'status' => 'pending',
        ]);
        $otherRequest = BrowserAccessRequest::create([
            'student_id' => $otherStudent->id,
            'student_device_id' => $otherDevice->id,
            'requested_url' => 'https://other.test/request',
            'host' => 'other.test',
            'registrable_domain' => 'other.test',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.students.browser-access-requests.destroy', [$student, $request]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Denied and hid blocked.test request.');

        $this->assertDatabaseMissing('browser_access_requests', [
            'id' => $request->id,
        ]);
        $this->assertDatabaseMissing('browser_policy_rules', [
            'student_id' => $student->id,
            'value' => 'blocked.test',
        ]);
        $this->assertDatabaseHas('browser_access_requests', [
            'id' => $otherRequest->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.students.browser-access-requests.destroy', [$student, $otherRequest]))
            ->assertNotFound();
    }

    public function test_admin_dashboard_includes_extension_url_for_student_cards(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_extension_dashboard',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'extension_dashboard_student',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Extension Dashboard Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('monitorStudents.0.extension_url', route('admin.extension.show', $student))
            );
    }
}
