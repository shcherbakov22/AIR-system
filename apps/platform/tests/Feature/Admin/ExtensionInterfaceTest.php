<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\BrowserAccessRequest;
use App\Models\BrowserPolicyRule;
use App\Models\BrowserVisitLog;
use App\Models\DeviceAttentionCalibrationSession;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\StudentSetting;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\Violation;
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
            'look_away_event_threshold' => 2,
            'look_away_event_count' => 1,
            'look_away_task_session_id' => null,
        ]);
        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'extension-device',
            'label' => 'Desk Extension',
            'platform' => 'windows',
            'internet_access_mode' => 'whitelist',
            'last_seen_at' => now(),
        ]);
        BrowserPolicyRule::create([
            'student_id' => $student->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'docs.python.org',
        ]);
        BrowserAccessRequest::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'requested_url' => 'https://laravel.com/docs',
            'host' => 'laravel.com',
            'registrable_domain' => 'laravel.com',
            'reason' => 'Need docs',
            'status' => 'pending',
        ]);
        BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'mode' => 'whitelist',
            'decision' => 'blocked',
            'url' => 'https://laravel.com/docs',
            'host' => 'laravel.com',
            'registrable_domain' => 'laravel.com',
            'page_title' => 'Laravel Docs',
            'visited_at' => now(),
        ]);
        DeviceAttentionCalibrationSession::create([
            'student_device_id' => $device->id,
            'session_uuid' => 'attention-session-1',
            'provider' => 'browser',
            'status' => 'model_ready',
            'sample_count' => 12,
            'started_at' => now()->subMinutes(5),
            'model_ready_at' => now(),
        ]);
        Violation::create([
            'student_id' => $student->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Repeated attention loss',
            'penalty_units' => 5,
            'occurred_at' => now(),
            'auto_generated_key' => 'look-away:task-session:99',
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
                ->where('focusedStudent.browser_accountability.rules.0.value', 'docs.python.org')
                ->where('focusedStudent.browser_accountability.access_requests.0.registrable_domain', 'laravel.com')
                ->where('focusedStudent.browser_accountability.recent_visits.0.decision', 'blocked')
                ->where('focusedStudent.attention.look_away_event_threshold', 2)
                ->where('focusedStudent.attention.violations.0.rule_title', 'Repeated attention loss')
                ->where('focusedStudent.devices.0.attention_calibrations.0.status', 'model_ready')
                ->where('task_allowlists.0.title', 'Coding')
                ->where('task_allowlists.0.domains.0', 'github.com')
                ->where('extension_download_url', route('companion.browser-extension.download'))
            );
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
