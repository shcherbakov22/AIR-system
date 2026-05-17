<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AiOverseerDecision;
use App\Models\AiOverseerMessage;
use App\Models\ActivityLog;
use App\Models\BrowserVisitLog;
use App\Models\DeviceActivityEvent;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_filter_activity_logs(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'activity_admin',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'activity_student',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Activity Student',
            'status' => 'active',
            'notes' => null,
        ]);
        $otherStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'other_activity_student',
        ]);
        $otherStudent = Student::create([
            'user_id' => $otherStudentUser->id,
            'display_name' => 'Other Activity Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on task',
            'description' => 'Stay on task.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.violations.store'), [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'occurred_at' => '2026-05-17T10:15',
            'notes' => 'Created for log test.',
        ]);

        ActivityLog::create([
            'occurred_at' => now(),
            'category' => 'tasks',
            'action' => 'task_started',
            'student_id' => $otherStudent->id,
            'actor_user_id' => $otherStudentUser->id,
            'description' => 'Task started: Other task',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'student_id' => $student->id,
            'actor_user_id' => $admin->id,
            'category' => 'violations',
            'action' => 'violation_created',
            'description' => 'Violation created: Stay on task',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.logs.index', [
                'student_id' => $student->id,
                'category' => 'violations',
                'action' => 'violation_created',
                'search' => 'Stay',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Logs/Index')
                ->where('filters.student_id', $student->id)
                ->where('filters.category', 'violations')
                ->where('filters.action', 'violation_created')
                ->has('students', 2)
                ->has('categories')
                ->has('actions')
                ->has('logs.data', 1)
                ->where('logs.data.0.student.display_name', 'Activity Student')
                ->where('logs.data.0.action', 'violation_created')
            );
    }

    public function test_students_cannot_view_admin_logs(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'blocked_logs_student',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Blocked Logs Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.logs.index'))
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_admin_can_clear_one_activity_log_category(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'clear_log_category_admin',
        ]);

        ActivityLog::create([
            'occurred_at' => now(),
            'category' => 'pages',
            'action' => 'page_opened',
            'actor_user_id' => $admin->id,
            'description' => 'Page opened: Admin Logs Index',
        ]);
        ActivityLog::create([
            'occurred_at' => now(),
            'category' => 'apps',
            'action' => 'app_opened',
            'description' => 'App opened: chrome.exe',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.logs.destroy-category'), ['category' => 'pages'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Cleared 1 pages log.');

        $this->assertDatabaseMissing('activity_logs', [
            'category' => 'pages',
            'action' => 'page_opened',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'apps',
            'action' => 'app_opened',
        ]);
    }

    public function test_repeated_page_opens_are_deduped_by_page_state_across_interleaved_tabs(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'page_dedupe_admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.logs.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.logs.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.logs.index', ['category' => 'pages']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.logs.index', ['category' => 'pages']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.logs.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertSame(
            2,
            ActivityLog::query()
                ->where('category', 'pages')
                ->where('action', 'page_opened')
                ->where('actor_user_id', $admin->id)
                ->where('metadata->route', 'admin.logs.index')
                ->count(),
        );

        $this->assertSame(
            1,
            ActivityLog::query()
                ->where('category', 'pages')
                ->where('action', 'page_opened')
                ->where('actor_user_id', $admin->id)
                ->where('metadata->route', 'admin.dashboard')
                ->count(),
        );

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'pages',
            'action' => 'page_opened',
            'actor_user_id' => $admin->id,
            'description' => 'Page opened: Admin Dashboard',
        ]);
    }

    public function test_overseer_actions_are_logged_and_filterable(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'overseer_log_admin',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'overseer_log_student',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Overseer Log Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $decision = AiOverseerDecision::create([
            'student_id' => $student->id,
            'requested_by_user_id' => $studentUser->id,
            'request_type' => 'remove_violation',
            'status' => 'conversation',
            'confidence' => 0,
            'model' => 'test-model',
            'prompt_version' => 'test-prompt',
        ]);

        AiOverseerMessage::create([
            'ai_overseer_decision_id' => $decision->id,
            'user_id' => $studentUser->id,
            'sender' => 'student',
            'body' => 'Please remove this violation.',
            'is_final_decision' => false,
        ]);

        $decision->update([
            'status' => 'mentor_review',
            'decision' => 'remove_violation',
            'confidence' => 72,
            'mentor_notified_at' => now(),
        ]);

        $decision->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
            'action_taken' => 'mentor_approved_violation_waived',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'overseer',
            'action' => 'overseer_conversation_started',
            'student_id' => $student->id,
            'actor_user_id' => $studentUser->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'overseer',
            'action' => 'overseer_student_message',
            'student_id' => $student->id,
            'actor_user_id' => $studentUser->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'overseer',
            'action' => 'overseer_reviewed',
            'student_id' => $student->id,
            'actor_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'overseer',
            'action' => 'overseer_action_taken',
            'student_id' => $student->id,
            'actor_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.logs.index', [
                'category' => 'overseer',
                'action' => 'overseer_reviewed',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category', 'overseer')
                ->where('filters.action', 'overseer_reviewed')
                ->has('logs.data', 1)
                ->where('logs.data.0.category', 'overseer')
                ->where('logs.data.0.action', 'overseer_reviewed')
            );
    }

    public function test_app_website_and_page_activity_are_logged(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'activity_stream_admin',
        ]);
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'activity_stream_student',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Activity Stream Student',
            'status' => 'active',
            'notes' => null,
        ]);
        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'activity-stream-device',
            'label' => 'Activity device',
            'platform' => 'windows',
        ]);

        DeviceActivityEvent::create([
            'student_device_id' => $device->id,
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'Code.exe'],
                ],
            ],
            'observed_at' => now()->subMinute(),
        ]);
        DeviceActivityEvent::create([
            'student_device_id' => $device->id,
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'Chrome.exe'],
                ],
            ],
            'observed_at' => now(),
        ]);
        DeviceActivityEvent::create([
            'student_device_id' => $device->id,
            'event_type' => 'focused_app',
            'app_name' => 'Chrome.exe',
            'window_title' => 'Example',
            'browser_domain' => 'example.com',
            'payload' => [],
            'observed_at' => now(),
        ]);
        DeviceActivityEvent::create([
            'student_device_id' => $device->id,
            'event_type' => 'focused_app',
            'app_name' => 'Chrome.exe',
            'window_title' => 'Example',
            'browser_domain' => 'example.com',
            'payload' => [],
            'observed_at' => now()->addSecond(),
        ]);
        DeviceActivityEvent::create([
            'student_device_id' => $device->id,
            'event_type' => 'focused_app',
            'app_name' => 'Chrome.exe',
            'window_title' => 'Different tab',
            'browser_domain' => 'example.com',
            'payload' => [],
            'observed_at' => now()->addSeconds(2),
        ]);
        DeviceActivityEvent::create([
            'student_device_id' => $device->id,
            'event_type' => 'focused_app',
            'app_name' => 'Code.exe',
            'window_title' => 'Editor',
            'payload' => [],
            'observed_at' => now()->addSeconds(3),
        ]);
        BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'mode' => 'blacklist',
            'decision' => 'allowed',
            'url' => 'https://example.com/lesson',
            'host' => 'example.com',
            'registrable_domain' => 'example.com',
            'page_title' => 'Example lesson',
            'meta' => ['source' => 'chrome_extension'],
            'visited_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.logs.index'))
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'apps',
            'action' => 'app_opened',
            'student_id' => $student->id,
            'description' => 'App opened: code.exe',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'apps',
            'action' => 'app_closed',
            'student_id' => $student->id,
            'description' => 'App closed: code.exe',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'apps',
            'action' => 'app_opened',
            'student_id' => $student->id,
            'description' => 'App opened: chrome.exe',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'apps',
            'action' => 'app_focused',
            'student_id' => $student->id,
            'description' => 'Focused app: Chrome.exe',
        ]);
        $this->assertSame(
            2,
            ActivityLog::query()
                ->where('category', 'apps')
                ->where('action', 'app_focused')
                ->where('student_id', $student->id)
                ->count(),
        );
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'websites',
            'action' => 'website_allowed',
            'student_id' => $student->id,
            'description' => 'Allowed website: example.com',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'pages',
            'action' => 'page_opened',
            'actor_user_id' => $admin->id,
            'description' => 'Page opened: Admin Logs Index',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.logs.index', ['category' => 'websites']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category', 'websites')
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'website_allowed')
            );
    }
}
