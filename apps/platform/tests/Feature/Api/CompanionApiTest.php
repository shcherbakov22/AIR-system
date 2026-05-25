<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\BrowserVisitLog;
use App\Models\BrowserPolicyRule;
use App\Models\DeviceEnrollmentToken;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\StudentSetting;
use App\Models\TaskTemplate;
use App\Models\TaskSession;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_enroll_device_and_receive_token(): void
    {
        [$student, $studentUser] = $this->makeStudent('companion_student', 'secret-pass');

        $response = $this->postJson(route('api.companion.enroll'), [
            'username' => $studentUser->username,
            'password' => 'secret-pass',
            'device_key' => 'device-alpha',
            'label' => 'Desk PC',
            'hostname' => 'desk-pc',
            'platform' => 'windows',
            'app_version' => '0.1.0',
            'meta' => [
                'build' => 'debug',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('student.id', $student->id)
            ->assertJsonPath('device.device_key', 'device-alpha');

        $this->assertNotEmpty($response->json('web.browser_login_url'));

        $this->assertDatabaseHas('student_devices', [
            'student_id' => $student->id,
            'device_key' => 'device-alpha',
            'label' => 'Desk PC',
            'platform' => 'windows',
        ]);

        $device = StudentDevice::query()->firstOrFail();
        $this->assertNotNull($device->token_hash);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_device_can_claim_one_time_enrollment_token(): void
    {
        [$student, $studentUser] = $this->makeStudent('token_student', 'secret-pass');
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'token_admin',
        ]);

        [$record, $plainTextToken] = DeviceEnrollmentToken::issue($student->id, $admin->id, null, 30);

        $response = $this->postJson(route('api.companion.enroll.claim'), [
            'enrollment_token' => $plainTextToken,
            'device_key' => 'device-token-student',
            'label' => 'Token PC',
            'hostname' => 'token-pc',
            'platform' => 'windows',
            'app_version' => '0.1.0',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('student.id', $student->id)
            ->assertJsonPath('student.username', $studentUser->username)
            ->assertJsonPath('device.device_key', 'device-token-student');

        $this->assertNotEmpty($response->json('web.browser_login_url'));

        $this->assertDatabaseHas('student_devices', [
            'student_id' => $student->id,
            'device_key' => 'device-token-student',
            'label' => 'Token PC',
        ]);

        $record->refresh();
        $this->assertNotNull($record->used_at);
        $this->assertNotNull($record->used_by_device_id);
    }

    public function test_policy_reports_internet_control_as_removed(): void
    {
        [$student, $studentUser] = $this->makeStudent('policy_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');

        $token = $device->issueToken();

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'));
        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.internet_policy.mode', 'allow_all')
            ->assertJsonPath('policy.internet_policy.internet_allowed', true)
            ->assertJsonPath('policy.internet_policy.reason', 'internet_control_removed')
            ->assertJsonPath('network_state.reason', 'internet_control_removed');
    }

    public function test_policy_ignores_legacy_device_internet_access_mode(): void
    {
        [$student, $studentUser] = $this->makeStudent('internet_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $device->update([
            'internet_access_mode' => 'block_all',
        ]);

        $response = $this->withHeaders($this->authHeaders($device->issueToken()))
            ->getJson(route('api.companion.policy.show'));

        $response
            ->assertOk()
            ->assertJsonPath('policy.internet_policy.mode', 'allow_all')
            ->assertJsonPath('policy.internet_policy.internet_allowed', true)
            ->assertJsonPath('policy.internet_policy.reason', 'internet_control_removed');
    }

    public function test_policy_keeps_internet_removed_when_feature_flags_change(): void
    {
        [$student, $studentUser] = $this->makeStudent('disabled_internet_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');

        $taskTemplate = TaskTemplate::create([
            'title' => 'Tennis',
            'summary' => null,
            'instructions' => 'Practice serves.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'created_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Tennis',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(1),
            'duration_seconds' => 60,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($device->issueToken()))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.internet_policy.mode', 'allow_all')
            ->assertJsonPath('policy.internet_policy.internet_allowed', true)
            ->assertJsonPath('policy.internet_policy.reason', 'internet_control_removed');
    }

    public function test_browser_policy_defaults_to_blacklist_and_logs_domain_tree_block(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_blacklist_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        BrowserPolicyRule::create([
            'student_id' => $student->id,
            'effect' => 'block',
            'match_type' => 'domain_tree',
            'value' => 'youtube.com',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.browser.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.mode', 'blacklist')
            ->assertJsonPath('policy.default_unblock_scope', 'domain_tree')
            ->assertJsonPath('policy.rules.0.value', 'youtube.com');

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.visits.store'), [
                'url' => 'https://music.youtube.com/watch?v=123',
                'page_title' => 'Music',
                'meta' => ['source' => 'explicit_navigation'],
            ])
            ->assertOk()
            ->assertJsonPath('visit.decision', 'blocked')
            ->assertJsonPath('visit.host', 'music.youtube.com')
            ->assertJsonPath('visit.registrable_domain', 'youtube.com');

        $this->assertDatabaseHas('browser_visit_logs', [
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'mode' => 'blacklist',
            'decision' => 'blocked',
            'host' => 'music.youtube.com',
            'registrable_domain' => 'youtube.com',
        ]);
    }

    public function test_browser_whitelist_blocks_unknown_domains_and_creates_access_request(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_whitelist_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $device->update(['internet_access_mode' => 'whitelist']);
        $token = $device->issueToken();

        BrowserPolicyRule::create([
            'student_id' => $student->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'khanacademy.org',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.visits.store'), [
                'url' => 'https://math.khanacademy.org/lesson',
            ])
            ->assertOk()
            ->assertJsonPath('visit.decision', 'allowed')
            ->assertJsonPath('visit.registrable_domain', 'khanacademy.org');

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.visits.store'), [
                'url' => 'https://docs.google.com/document/d/abc',
            ])
            ->assertOk()
            ->assertJsonPath('visit.decision', 'blocked')
            ->assertJsonPath('visit.registrable_domain', 'google.com');

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.access-requests.store'), [
                'url' => 'https://docs.google.com/document/d/abc',
                'reason' => 'Need this document for math.',
            ])
            ->assertAccepted()
            ->assertJsonPath('request.status', 'denied')
            ->assertJsonPath('request.registrable_domain', 'google.com');

        $this->assertDatabaseHas('browser_access_requests', [
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'host' => 'docs.google.com',
            'registrable_domain' => 'google.com',
            'status' => 'denied',
        ]);
    }

    public function test_browser_whitelist_uses_active_task_template_domains(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_task_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $device->update(['internet_access_mode' => 'whitelist']);
        $token = $device->issueToken();

        $tennisTemplate = TaskTemplate::create([
            'title' => 'Tennis',
            'summary' => null,
            'instructions' => 'Practice serves.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'created_by_user_id' => $studentUser->id,
        ]);

        $codingTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build the project.',
            'default_duration_minutes' => 45,
            'requires_internet' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        BrowserPolicyRule::create([
            'student_id' => null,
            'task_template_id' => $codingTemplate->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'github.com',
        ]);

        $tennisSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $tennisTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Tennis',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(5),
            'duration_seconds' => 300,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.browser.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.active_task.title', 'Tennis')
            ->assertJsonCount(0, 'policy.rules');

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.visits.store'), [
                'url' => 'https://github.com/laravel/framework',
            ])
            ->assertOk()
            ->assertJsonPath('visit.decision', 'blocked');

        $tennisSession->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $codingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 45,
            'started_at' => now(),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.browser.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.active_task.title', 'Coding')
            ->assertJsonPath('policy.rules.0.value', 'github.com')
            ->assertJsonPath('policy.rules.0.scope', 'task_template');

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.visits.store'), [
                'url' => 'https://docs.github.com/actions',
            ])
            ->assertOk()
            ->assertJsonPath('visit.decision', 'allowed');
    }

    public function test_blocked_browser_visit_creates_violation_for_active_task_rule(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_block_violation_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $codingTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build the project.',
            'default_duration_minutes' => 45,
            'requires_internet' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $codingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 45,
            'started_at' => now()->subMinutes(5),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        BrowserPolicyRule::create([
            'student_id' => null,
            'task_template_id' => $codingTemplate->id,
            'effect' => 'block',
            'match_type' => 'domain_tree',
            'value' => 'youtube.com',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.visits.store'), [
                'url' => 'https://music.youtube.com/watch?v=123',
                'page_title' => 'Music',
                'meta' => ['source' => 'explicit_navigation'],
            ])
            ->assertOk()
            ->assertJsonPath('visit.decision', 'blocked');

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Blocked website opened',
        ]);

        $violation = Violation::query()
            ->where('student_id', $student->id)
            ->where('rule_title_snapshot', 'Blocked website opened')
            ->sole();

        $this->assertStringContainsString('youtube.com', (string) $violation->notes);
        $this->assertStringContainsString('Coding', (string) $violation->notes);
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'websites',
            'action' => 'blocked_website_violation',
            'student_id' => $student->id,
            'subject_type' => Violation::class,
            'subject_id' => $violation->id,
            'description' => 'Blocked website violation: youtube.com',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.visits.store'), [
                'url' => 'https://www.youtube.com/shorts/abc',
                'page_title' => 'Shorts',
                'meta' => ['source' => 'explicit_navigation'],
            ])
            ->assertOk()
            ->assertJsonPath('visit.decision', 'blocked');

        $this->assertSame(1, Violation::query()
            ->where('student_id', $student->id)
            ->where('rule_title_snapshot', 'Blocked website opened')
            ->where('status', 'open')
            ->count());
    }

    public function test_blocked_browser_visit_from_policy_enforcement_does_not_create_violation(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_block_enforced_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $codingTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build the project.',
            'default_duration_minutes' => 45,
            'requires_internet' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $codingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 45,
            'started_at' => now()->subMinutes(5),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        BrowserPolicyRule::create([
            'student_id' => null,
            'task_template_id' => $codingTemplate->id,
            'effect' => 'block',
            'match_type' => 'domain_tree',
            'value' => 'youtube.com',
        ]);

        foreach (['policy_enforcement', 'content_script', 'chrome_extension'] as $source) {
            $this->withHeaders($this->authHeaders($token))
                ->postJson(route('api.companion.browser.visits.store'), [
                    'url' => 'https://music.youtube.com/watch?v='.$source,
                    'page_title' => 'Music',
                    'meta' => ['source' => $source],
                ])
                ->assertOk()
                ->assertJsonPath('visit.decision', 'blocked');
        }

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Blocked website opened',
        ]);
    }

    public function test_browser_access_approval_adds_domain_to_active_task_template(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_task_request_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'browser_task_request_admin',
        ]);

        $codingTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build the project.',
            'default_duration_minutes' => 45,
            'requires_internet' => true,
            'created_by_user_id' => $admin->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $codingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 45,
            'started_at' => now(),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $requestResponse = $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.access-requests.store'), [
                'url' => 'https://docs.github.com/actions',
                'reason' => 'Need docs for coding.',
            ])
            ->assertCreated()
            ->assertJsonPath('request.task_template_id', $codingTemplate->id);

        $accessRequestId = $requestResponse->json('request.id');

        $this->actingAs($admin)
            ->patch(route('admin.students.browser-access-requests.approve', [$student, $accessRequestId]))
            ->assertRedirect();

        $this->assertDatabaseHas('browser_policy_rules', [
            'student_id' => null,
            'task_template_id' => $codingTemplate->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'github.com',
        ]);
    }

    public function test_browser_access_request_without_active_task_is_denied_automatically(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_no_task_request_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $requestResponse = $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.access-requests.store'), [
                'url' => 'https://docs.github.com/actions',
                'reason' => 'No task is open.',
            ])
            ->assertAccepted()
            ->assertJsonPath('request.status', 'denied')
            ->assertJsonPath('request.task_template_id', null);

        $this->assertDatabaseHas('browser_access_requests', [
            'id' => $requestResponse->json('request.id'),
            'student_id' => $student->id,
            'status' => 'denied',
            'task_template_id' => null,
        ]);
    }

    public function test_browser_access_approval_uses_task_from_request_time_after_task_switch(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_switched_task_request_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'browser_switched_task_request_admin',
        ]);

        $originalTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build the project.',
            'default_duration_minutes' => 45,
            'requires_internet' => true,
            'created_by_user_id' => $admin->id,
        ]);
        $newTemplate = TaskTemplate::create([
            'title' => 'Reading',
            'summary' => null,
            'instructions' => 'Read.',
            'default_duration_minutes' => 30,
            'requires_internet' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $originalSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $originalTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 45,
            'started_at' => now()->subMinutes(10),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $requestResponse = $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.access-requests.store'), [
                'url' => 'https://docs.github.com/actions',
            ])
            ->assertCreated()
            ->assertJsonPath('request.task_template_id', $originalTemplate->id);

        $originalSession->update(['status' => 'completed', 'ended_at' => now()]);
        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $newTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 30,
            'started_at' => now(),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.students.browser-access-requests.approve', [$student, $requestResponse->json('request.id')]))
            ->assertRedirect();

        $this->assertDatabaseHas('browser_policy_rules', [
            'student_id' => null,
            'task_template_id' => $originalTemplate->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'github.com',
        ]);
        $this->assertDatabaseMissing('browser_policy_rules', [
            'task_template_id' => $newTemplate->id,
            'value' => 'github.com',
        ]);
    }

    public function test_mentor_can_switch_browser_mode_for_student_devices(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_mode_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'browser_mode_admin',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.students.browser-mode.update', $student), [
                'mode' => 'whitelist',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('student_devices', [
            'id' => $device->id,
            'internet_access_mode' => 'whitelist',
        ]);
    }

    public function test_manual_whitelist_rule_defaults_to_active_task_template_scope(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_manual_task_rule_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $device->update(['internet_access_mode' => 'whitelist']);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'browser_manual_task_rule_admin',
        ]);

        $codingTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build the project.',
            'default_duration_minutes' => 45,
            'requires_internet' => true,
            'created_by_user_id' => $admin->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $codingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 45,
            'started_at' => now(),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.students.browser-rules.store', $student), [
                'effect' => 'allow',
                'value' => 'https://docs.github.com/actions',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('browser_policy_rules', [
            'student_id' => null,
            'task_template_id' => $codingTemplate->id,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'github.com',
        ]);

        $this->assertDatabaseMissing('browser_policy_rules', [
            'student_id' => $student->id,
            'task_template_id' => null,
            'effect' => 'allow',
            'value' => 'github.com',
        ]);
    }

    public function test_mentor_approval_allows_requested_domain_and_all_subdomains(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_approval_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'browser_approval_admin',
        ]);
        $taskTemplate = TaskTemplate::create([
            'title' => 'Research',
            'summary' => null,
            'instructions' => 'Research.',
            'default_duration_minutes' => 45,
            'requires_internet' => true,
            'created_by_user_id' => $admin->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Research',
            'planned_duration_minutes' => 45,
            'started_at' => now(),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $requestResponse = $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser.access-requests.store'), [
                'url' => 'https://math.khanacademy.org/lesson/123',
            ]);

        $accessRequestId = $requestResponse->json('request.id');

        $this->actingAs($admin)
            ->patch(route('admin.students.browser-access-requests.approve', [$student, $accessRequestId]), [
                'global' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('browser_policy_rules', [
            'student_id' => $student->id,
            'task_template_id' => null,
            'effect' => 'allow',
            'match_type' => 'domain_tree',
            'value' => 'khanacademy.org',
        ]);

        $this->assertDatabaseHas('browser_access_requests', [
            'id' => $accessRequestId,
            'status' => 'approved',
            'registrable_domain' => 'khanacademy.org',
        ]);
    }

    public function test_policy_enables_gui_kill_for_any_open_violation_immediately(): void
    {
        [$student, $studentUser] = $this->makeStudent('stale_violation_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');

        $violation = Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => null,
            'rule_title_snapshot' => 'Recent violation',
            'status' => 'open',
            'penalty_units' => 5,
            'push_up_count' => 5,
            'occurred_at' => now()->subMinutes(20),
        ]);

        $recentResponse = $this->withHeaders($this->authHeaders($device->issueToken()))
            ->getJson(route('api.companion.policy.show'));

        $recentResponse
            ->assertOk()
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', true)
            ->assertJsonPath('policy.violation_app_enforcement.browser_reopen_grace_seconds', 60);
    }

    public function test_device_can_post_heartbeat_activity_and_capture(): void
    {
        Storage::fake('local');

        [$student, $studentUser] = $this->makeStudent('capture_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $taskTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build features.',
            'default_duration_minutes' => 45,
            'requires_internet' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 45,
            'started_at' => now()->subMinutes(4),
            'duration_seconds' => 240,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.heartbeat'), [
                'label' => 'Desk PC',
                'hostname' => 'desk-pc',
                'app_version' => '0.1.0',
                'ipv4' => '192.168.11.44',
                'mac_address' => 'aa:bb:cc:dd:ee:ff',
                'gateway_ipv4' => '192.168.11.228',
                'network_adapter_name' => 'Ethernet 1',
                'meta' => ['health' => 'ok'],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'focused_app',
                'app_name' => 'Code.exe',
                'window_title' => 'AIR System',
                'browser_domain' => 'github.com',
                'payload' => ['pid' => 1234],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $captureResponse = $this->withHeaders($this->authHeaders($token))
            ->post(route('api.companion.captures.screen'), [
                'capture' => UploadedFile::fake()->create('screen.jpg', 256, 'image/jpeg'),
                'captured_at' => now()->toAtomString(),
                'app_name' => 'Code.exe',
                'window_title' => 'AIR System',
                'browser_domain' => 'github.com',
            ]);

        $captureResponse
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseCount('device_heartbeats', 1);
        $this->assertDatabaseHas('student_devices', [
            'id' => $device->id,
            'last_ipv4' => '192.168.11.44',
            'last_mac_address' => 'aa:bb:cc:dd:ee:ff',
            'last_gateway_ipv4' => '192.168.11.228',
            'network_adapter_name' => 'Ethernet 1',
        ]);
        $this->assertDatabaseHas('device_activity_events', [
            'student_device_id' => $device->id,
            'event_type' => 'focused_app',
            'app_name' => 'Code.exe',
            'browser_domain' => 'github.com',
        ]);
        $this->assertDatabaseHas('student_monitor_captures', [
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'capture_kind' => 'screen',
            'task_title_snapshot' => 'Coding',
            'app_name_snapshot' => 'Code.exe',
            'browser_domain_snapshot' => 'github.com',
        ]);
    }

    public function test_device_can_create_attention_calibration_session_upload_batches_and_check_status(): void
    {
        [$student, $studentUser] = $this->makeStudent('attention_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $startResponse = $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.attention.sessions.start'), [
                'provider' => 'eyetheia',
                'meta' => [
                    'camera_label' => 'Integrated Webcam',
                ],
            ]);

        $startResponse
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('session.provider', 'eyetheia')
            ->assertJsonPath('session.status', 'collecting');

        $sessionUuid = $startResponse->json('session.session_uuid');

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.attention.sessions.batches.store', $sessionUuid), [
                'samples' => [
                    [
                        'timestamp_ms' => 1000,
                        'pose' => ['yaw' => 0.1, 'pitch' => -0.2, 'roll' => 0.0],
                        'eyes' => ['leftOpenRatio' => 0.2, 'rightOpenRatio' => 0.19],
                        'screen_target' => ['x' => 0.5, 'y' => 0.5],
                    ],
                    [
                        'timestamp_ms' => 1150,
                        'pose' => ['yaw' => 0.0, 'pitch' => -0.1, 'roll' => 0.0],
                        'eyes' => ['leftOpenRatio' => 0.21, 'rightOpenRatio' => 0.20],
                        'screen_target' => ['x' => 0.5, 'y' => 0.5],
                    ],
                ],
                'finalize' => true,
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('session.status', 'queued')
            ->assertJsonPath('session.sample_count', 2);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.attention.status'))
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('latest_session.session_uuid', $sessionUuid)
            ->assertJsonPath('latest_session.status', 'queued')
            ->assertJsonPath('latest_session.sample_count', 2)
            ->assertJsonPath('model.status', 'queued');

        $this->assertDatabaseHas('device_attention_calibration_sessions', [
            'student_device_id' => $device->id,
            'session_uuid' => $sessionUuid,
            'provider' => 'eyetheia',
            'status' => 'queued',
            'sample_count' => 2,
        ]);

        $this->assertDatabaseHas('device_attention_calibration_batches', [
            'sample_count' => 2,
        ]);
    }

    public function test_device_attention_events_record_look_away_events_without_creating_violation(): void
    {
        [$student, $studentUser] = $this->makeStudent('look_away_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $student->setting()->create([
            'can_manage_own_schedule' => true,
            'can_use_ad_hoc_timer' => true,
            'look_away_event_threshold' => 2,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
            'preferred_timezone' => 'UTC',
        ]);

        RuleDefinition::create([
            'title' => 'Look away',
            'description' => 'Automatic attention-loss violation.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Stay on task.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'can_end_early' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        $activeTaskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(2),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.attention.events.store'), [
                'event_type' => 'look_away',
                'payload' => [
                    'reason' => 'look_away',
                    'score' => 3.2,
                    'away_seconds' => 2.4,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('triggered_violation', false)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('threshold', 2)
            ->assertJsonPath('reason', 'look_away_recorded');

        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'student_id' => $student->id,
            'category' => 'attention',
            'action' => 'look_away_event',
            'subject_type' => TaskSession::class,
            'subject_id' => $activeTaskSession->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.attention.events.store'), [
                'event_type' => 'look_away',
                'payload' => [
                    'reason' => 'look_away',
                    'score' => 3.4,
                    'away_seconds' => 2.7,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('triggered_violation', false)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('threshold', 2)
            ->assertJsonPath('reason', 'look_away_recorded');

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'auto_generated_key' => 'look-away:task-session:'.$activeTaskSession->id,
        ]);
        $this->assertSame(2, ActivityLog::query()
            ->where('student_id', $student->id)
            ->where('category', 'attention')
            ->where('action', 'look_away_event')
            ->where('subject_type', TaskSession::class)
            ->where('subject_id', $activeTaskSession->id)
            ->count());

        $activeTaskSession->refresh();
        $this->assertSame('active', $activeTaskSession->status);

        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
        ]);

        $manualTaskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Manual stop task',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinute(),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $student->setting()->update([
            'look_away_event_count' => 0,
            'look_away_task_session_id' => $manualTaskSession->id,
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.task-sessions.stop', $manualTaskSession))
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
        ]);
    }

    public function test_look_away_events_are_recorded_per_student_and_do_not_finish_tasks(): void
    {
        [$student, $studentUser] = $this->makeStudent('lookaway_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        StudentSetting::create([
            'student_id' => $student->id,
            'can_manage_own_schedule' => true,
            'can_use_ad_hoc_timer' => true,
            'look_away_event_threshold' => 2,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
            'preferred_timezone' => 'UTC',
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'lookaway_admin',
        ]);

        RuleDefinition::create([
            'title' => 'Look away',
            'description' => 'Repeated attention loss.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Math',
            'summary' => null,
            'instructions' => 'Read carefully.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'created_by_user_id' => $studentUser->id,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Math',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(3),
            'duration_seconds' => 180,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.attention.events.store'), [
                'event_type' => 'look_away',
                'payload' => [
                    'reason' => 'look_away',
                    'score' => 3.5,
                    'away_seconds' => 2.8,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('triggered_violation', false)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('threshold', 2)
            ->assertJsonPath('reason', 'look_away_recorded');

        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.attention.events.store'), [
                'event_type' => 'look_away',
                'payload' => [
                    'reason' => 'look_away',
                    'score' => 3.7,
                    'away_seconds' => 3.1,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('triggered_violation', false)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('reason', 'look_away_recorded');

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'auto_generated_key' => 'look-away:task-session:'.$taskSession->id,
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'id' => $taskSession->id,
            'status' => 'active',
        ]);
        $this->assertSame(2, ActivityLog::query()
            ->where('student_id', $student->id)
            ->where('category', 'attention')
            ->where('action', 'look_away_event')
            ->where('subject_type', TaskSession::class)
            ->where('subject_id', $taskSession->id)
            ->count());

        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
        ]);
    }

    public function test_body_missing_event_creates_attention_violation_after_threshold(): void
    {
        config(['services.attention_tracking.body_missing_violation_seconds' => 10]);

        [$student, $studentUser] = $this->makeStudent('body_missing_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        StudentSetting::create([
            'student_id' => $student->id,
            'can_manage_own_schedule' => true,
            'can_use_ad_hoc_timer' => true,
            'look_away_event_threshold' => 20,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
            'preferred_timezone' => 'UTC',
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'body_missing_admin',
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Left camera view',
            'description' => 'Student left the camera view.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'History',
            'summary' => null,
            'instructions' => 'Stay visible.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'created_by_user_id' => $studentUser->id,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'History',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(4),
            'duration_seconds' => 240,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.attention.events.store'), [
                'event_type' => 'body_missing',
                'payload' => [
                    'reason' => 'body_missing',
                    'score' => 0,
                    'body_confidence' => 0,
                    'away_seconds' => 9.9,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('triggered_violation', false)
            ->assertJsonPath('reason', 'threshold_not_reached')
            ->assertJsonPath('threshold_seconds', 10);

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'auto_generated_key' => 'body-missing:task-session:'.$taskSession->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.attention.events.store'), [
                'event_type' => 'body_missing',
                'payload' => [
                    'reason' => 'body_missing',
                    'score' => 0,
                    'body_confidence' => 0,
                    'away_seconds' => 10.2,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('triggered_violation', true)
            ->assertJsonPath('reason', 'violation_created')
            ->assertJsonPath('threshold_seconds', 10);

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Left camera view',
            'auto_generated_key' => 'body-missing:task-session:'.$taskSession->id,
        ]);

        $this->assertDatabaseHas('task_sessions', [
            'id' => $taskSession->id,
            'status' => 'unfinished',
        ]);

        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
        ]);
    }

    public function test_body_missing_violations_are_ignored_for_untracked_tasks_while_look_away_is_logged(): void
    {
        config(['services.attention_tracking.body_missing_violation_seconds' => 10]);

        [$student, $studentUser] = $this->makeStudent('untracked_attention_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        StudentSetting::create([
            'student_id' => $student->id,
            'can_manage_own_schedule' => true,
            'can_use_ad_hoc_timer' => true,
            'look_away_event_threshold' => 1,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
            'preferred_timezone' => 'UTC',
        ]);

        RuleDefinition::create([
            'title' => 'Look away',
            'description' => 'Repeated attention loss.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        RuleDefinition::create([
            'title' => 'Left camera view',
            'description' => 'Student left the camera view.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Reading',
            'summary' => null,
            'instructions' => 'Read.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'created_by_user_id' => $studentUser->id,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(3),
            'duration_seconds' => 180,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.attention.events.store'), [
                'event_type' => 'look_away',
                'payload' => [
                    'reason' => 'look_away',
                    'score' => 4,
                    'away_seconds' => 3,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('triggered_violation', false)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('reason', 'look_away_recorded');

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.attention.events.store'), [
                'event_type' => 'body_missing',
                'payload' => [
                    'reason' => 'body_missing',
                    'score' => 0,
                    'body_confidence' => 0,
                    'away_seconds' => 20,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('triggered_violation', false)
            ->assertJsonPath('reason', 'task_not_attention_tracked');

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'auto_generated_key' => 'look-away:task-session:'.$taskSession->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'student_id' => $student->id,
            'category' => 'attention',
            'action' => 'look_away_event',
            'subject_type' => TaskSession::class,
            'subject_id' => $taskSession->id,
        ]);
        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'auto_generated_key' => 'body-missing:task-session:'.$taskSession->id,
        ]);
        $this->assertDatabaseHas('task_sessions', [
            'id' => $taskSession->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('student_settings', [
            'student_id' => $student->id,
            'look_away_event_count' => 0,
            'look_away_task_session_id' => null,
        ]);
    }

    public function test_first_open_apps_snapshot_is_grandfathered_and_later_new_apps_require_review(): void
    {
        [$student, $studentUser] = $this->makeStudent('app_review_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'Code.exe',
                            'window_title' => 'AIR System',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'app_key' => 'code.exe',
            'app_name' => 'Code.exe',
            'status' => 'permitted',
        ]);

        $device->refresh();
        $this->assertNotNull(data_get($device->meta, 'app_policy_initialized_at'));

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'Code.exe',
                            'window_title' => 'AIR System',
                        ],
                        [
                            'app_name' => 'Steam.exe',
                            'window_title' => 'Steam',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'app_key' => 'steam.exe',
            'app_name' => 'Steam.exe',
            'status' => 'pending_review',
        ]);

        $policyResponse = $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'));

        $policyResponse
            ->assertOk()
            ->assertJsonPath('policy.app_control.mode', 'review')
            ->assertJsonPath('policy.app_control.blocked_processes', ['Steam.exe']);

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Blocked program opened',
        ]);
    }

    public function test_open_app_review_is_scoped_to_active_task_template(): void
    {
        [$student, $studentUser] = $this->makeStudent('task_app_scope_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'task_app_scope_admin',
        ]);
        $codingTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build.',
            'default_duration_minutes' => 45,
            'created_by_user_id' => $admin->id,
        ]);
        $readingTemplate = TaskTemplate::create([
            'title' => 'Reading',
            'summary' => null,
            'instructions' => 'Read.',
            'default_duration_minutes' => 30,
            'created_by_user_id' => $admin->id,
        ]);

        $codingSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $codingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 45,
            'started_at' => now()->subMinutes(5),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $device->forceFill(['meta' => ['app_policy_initialized_at' => now()->subHour()->toAtomString()]])->save();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => ['apps' => [['app_name' => 'Blender.exe']]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'task_template_id' => $codingTemplate->id,
            'app_key' => 'blender.exe',
            'status' => 'pending_review',
        ]);

        $policy = $student->appPolicies()->where('app_key', 'blender.exe')->firstOrFail();
        $this->actingAs($admin)
            ->patch(route('admin.students.app-policies.permit', [$student, $policy]))
            ->assertRedirect();

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonCount(0, 'policy.app_control.blocked_processes');

        $codingSession->update(['status' => 'completed', 'ended_at' => now()]);
        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $readingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 30,
            'started_at' => now(),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => ['apps' => [['app_name' => 'Blender.exe']]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'task_template_id' => $readingTemplate->id,
            'app_key' => 'blender.exe',
            'status' => 'pending_review',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.app_control.blocked_processes', ['Blender.exe']);
    }

    public function test_blocked_open_app_creates_violation_for_active_task_policy(): void
    {
        [$student, $studentUser] = $this->makeStudent('blocked_app_violation_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'blocked_app_violation_admin',
        ]);
        $codingTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build.',
            'default_duration_minutes' => 45,
            'created_by_user_id' => $admin->id,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $codingTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 45,
            'started_at' => now()->subMinutes(5),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $device->forceFill(['meta' => ['app_policy_initialized_at' => now()->subHour()->toAtomString()]])->save();
        $student->appPolicies()->create([
            'task_template_id' => $codingTemplate->id,
            'app_key' => 'steam.exe',
            'app_name' => 'Steam.exe',
            'status' => 'blocked',
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now()->subHour(),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => ['apps' => [['app_name' => 'Steam.exe']]],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Blocked program opened',
        ]);

        $violation = Violation::query()
            ->where('student_id', $student->id)
            ->where('rule_title_snapshot', 'Blocked program opened')
            ->sole();

        $this->assertStringContainsString('Steam.exe', (string) $violation->notes);
        $this->assertStringContainsString('Coding', (string) $violation->notes);
        $this->assertDatabaseHas('activity_logs', [
            'category' => 'apps',
            'action' => 'blocked_program_violation',
            'student_id' => $student->id,
            'subject_type' => Violation::class,
            'subject_id' => $violation->id,
            'description' => 'Blocked program violation: Steam.exe',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => ['apps' => [['app_name' => 'Steam.exe']]],
            ])
            ->assertOk();

        $this->assertSame(1, Violation::query()
            ->where('student_id', $student->id)
            ->where('rule_title_snapshot', 'Blocked program opened')
            ->where('status', 'open')
            ->count());
    }

    public function test_app_policy_can_be_promoted_to_global_permission(): void
    {
        [$student, $studentUser] = $this->makeStudent('global_app_scope_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'global_app_scope_admin',
        ]);
        $taskTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Build.',
            'default_duration_minutes' => 45,
            'created_by_user_id' => $admin->id,
        ]);
        $policy = $student->appPolicies()->create([
            'task_template_id' => $taskTemplate->id,
            'app_key' => 'code.exe',
            'app_name' => 'Code.exe',
            'status' => 'pending_review',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'grace_deadline_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.students.app-policies.permit', [$student, $policy]), [
                'global' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'task_template_id' => null,
            'app_key' => 'code.exe',
            'status' => 'permitted',
        ]);
        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'app_key' => 'code.exe',
            'status' => 'pending_review',
        ]);
    }

    public function test_installed_apps_inventory_is_persisted_and_grandfathered_as_permitted(): void
    {
        [$student, $studentUser] = $this->makeStudent('installed_apps_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'installed_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'Code.exe',
                            'display_name' => 'Visual Studio Code',
                            'display_version' => '1.2.3',
                            'publisher' => 'Microsoft',
                            'install_location' => 'C:\\Program Files\\VS Code',
                            'source' => 'registry_uninstall',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('student_device_installed_apps', [
            'student_device_id' => $device->id,
            'app_key' => 'code.exe',
            'display_name' => 'Visual Studio Code',
            'display_version' => '1.2.3',
            'publisher' => 'Microsoft',
            'install_location' => 'C:\\Program Files\\VS Code',
        ]);

        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'app_key' => 'code.exe',
            'app_name' => 'Code.exe',
            'status' => 'permitted',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'Code.exe',
                            'window_title' => 'AIR System',
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'open_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'Game.exe',
                            'window_title' => 'New Game',
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $policy = $student->appPolicies()->where('app_key', 'game.exe')->firstOrFail();
        $policy->update([
            'grace_deadline_at' => now()->subSecond(),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.app_control.blocked_processes.0', 'Game.exe');
    }

    public function test_installed_apps_seen_after_initialization_require_review_by_executable_name(): void
    {
        [$student, $studentUser] = $this->makeStudent('installed_apps_after_init_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $device->forceFill(['meta' => ['app_policy_initialized_at' => now()->subHour()->toAtomString()]])->save();
        $token = $device->issueToken();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'installed_apps',
                'payload' => [
                    'apps' => [
                        [
                            'app_name' => 'C:\\Users\\Dima\\Downloads\\ZoomInstaller.exe,0',
                            'display_name' => 'Zoom',
                            'display_version' => '6.0.0',
                            'publisher' => 'Zoom',
                            'install_location' => 'C:\\Users\\Dima\\Downloads',
                            'source' => 'registry_uninstall',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('student_device_installed_apps', [
            'student_device_id' => $device->id,
            'app_key' => 'zoominstaller.exe',
            'display_name' => 'Zoom',
        ]);

        $this->assertDatabaseHas('student_app_policies', [
            'student_id' => $student->id,
            'task_template_id' => null,
            'app_key' => 'zoominstaller.exe',
            'app_name' => 'ZoomInstaller.exe',
            'status' => 'pending_review',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.app_control.blocked_processes', ['ZoomInstaller.exe']);
    }

    public function test_app_enforcement_failures_are_logged_without_student_violation(): void
    {
        [$student, $studentUser] = $this->makeStudent('app_enforcement_log_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.activity.store'), [
                'event_type' => 'app_enforcement',
                'payload' => [
                    'failures' => [
                        [
                            'app_name' => 'ZoomInstaller.exe',
                            'reason' => 'terminate_failed',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('activity_logs', [
            'student_id' => $student->id,
            'category' => 'apps',
            'action' => 'app_enforcement_failed',
            'description' => 'App enforcement failed for 1 process(es).',
        ]);

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'rule_title_snapshot' => 'Blocked program opened',
        ]);
    }

    public function test_protected_shell_and_companion_apps_except_task_manager_are_never_emitted_as_blocked_processes(): void
    {
        [$student, $studentUser] = $this->makeStudent('protected_apps_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $student->appPolicies()->create([
            'app_key' => 'explorer.exe',
            'app_name' => 'explorer.exe',
            'status' => 'blocked',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $student->appPolicies()->create([
            'app_key' => 'rundll32.exe',
            'app_name' => 'rundll32.exe',
            'status' => 'blocked',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $student->appPolicies()->create([
            'app_key' => 'air_companion_tray.exe',
            'app_name' => 'air_companion_tray.exe',
            'status' => 'pending_review',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $student->appPolicies()->create([
            'app_key' => 'air_companion_service.exe',
            'app_name' => 'air_companion_service.exe',
            'status' => 'blocked',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $student->appPolicies()->create([
            'app_key' => 'game.exe',
            'app_name' => 'Game.exe',
            'status' => 'blocked',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $student->appPolicies()->create([
            'app_key' => 'taskmgr.exe',
            'app_name' => 'taskmgr.exe',
            'status' => 'blocked',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.app_control.blocked_processes', ['Game.exe', 'taskmgr.exe']);
    }

    public function test_unconfigured_browser_extension_reports_violation_and_blocks_browsers(): void
    {
        [$student, $studentUser] = $this->makeStudent('unconfigured_extension_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'label' => 'Chrome browser extension',
            'hostname' => null,
            'platform' => 'chrome_extension',
            'app_version' => '0.1.0',
            'last_seen_at' => null,
            'last_seen_ip' => '192.168.11.50',
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.violations.open_count', 0)
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', false);

        $device->activityEvents()->create([
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'chrome.exe', 'window_title' => 'IXL'],
                ],
            ],
            'observed_at' => now()->subSeconds(61),
        ]);
        $device->activityEvents()->create([
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'chrome.exe', 'window_title' => 'IXL refreshed'],
                ],
            ],
            'observed_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.violations.open_count', 1)
            ->assertJsonPath('policy.violations.items.0.rule_title', 'Browser extension removed')
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', true)
            ->assertJsonPath('policy.violation_app_enforcement.browser_reopen_grace_seconds', 0);
    }

    public function test_missing_browser_extension_record_does_not_block_before_initial_install(): void
    {
        [$student, $studentUser] = $this->makeStudent('missing_extension_record_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $device->activityEvents()->create([
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'chrome.exe', 'window_title' => 'IXL'],
                ],
            ],
            'observed_at' => now()->subSeconds(61),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.violations.open_count', 0)
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', false)
            ->assertJsonPath('policy.violation_app_enforcement.browser_reopen_grace_seconds', 60);
    }

    public function test_stale_installed_browser_extension_reports_violation_and_blocks_browsers(): void
    {
        [$student, $studentUser] = $this->makeStudent('missing_extension_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'label' => 'Chrome browser extension',
            'hostname' => null,
            'platform' => 'chrome_extension',
            'app_version' => '0.1.0',
            'last_seen_at' => now()->subMinutes(4),
            'last_seen_ip' => '192.168.11.50',
        ]);

        $device->activityEvents()->create([
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'chrome.exe', 'window_title' => 'IXL'],
                ],
            ],
            'observed_at' => now()->subSeconds(61),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.violations.open_count', 1)
            ->assertJsonPath('policy.violations.items.0.rule_title', 'Browser extension removed')
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', true)
            ->assertJsonPath('policy.violation_app_enforcement.browser_reopen_grace_seconds', 0);
    }

    public function test_stale_browser_extension_does_not_keep_browser_in_a_dead_restart_loop(): void
    {
        [$student, $studentUser] = $this->makeStudent('closed_browser_extension_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'label' => 'Chrome browser extension',
            'hostname' => null,
            'platform' => 'chrome_extension',
            'app_version' => '0.1.0',
            'last_seen_at' => now()->subMinutes(30),
            'last_seen_ip' => '192.168.11.50',
        ]);

        $device->activityEvents()->create([
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'chrome.exe', 'window_title' => 'Old browser window'],
                ],
            ],
            'observed_at' => now()->subMinutes(5),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.violations.open_count', 0)
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', false);
    }

    public function test_recent_browser_extension_heartbeat_prevents_false_missing_extension_enforcement(): void
    {
        [$student, $studentUser] = $this->makeStudent('active_extension_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id.':old',
            'label' => 'Old Chrome browser extension',
            'hostname' => null,
            'platform' => 'chrome_extension',
            'app_version' => '0.1.0',
            'last_seen_at' => now()->subMinutes(45),
            'last_seen_ip' => '192.168.11.51',
        ]);

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'label' => 'Chrome browser extension',
            'hostname' => null,
            'platform' => 'chrome_extension',
            'app_version' => '0.1.0',
            'last_seen_at' => now()->subMinute(),
            'last_seen_ip' => '192.168.11.52',
        ]);

        BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => StudentDevice::query()
                ->where('device_key', 'browser-extension:student:'.$student->id)
                ->value('id'),
            'matched_rule_id' => null,
            'mode' => 'blacklist',
            'decision' => 'allowed',
            'url' => 'https://ixl.com/',
            'host' => 'ixl.com',
            'registrable_domain' => 'ixl.com',
            'page_title' => 'IXL',
            'meta' => ['source' => 'content_script'],
            'visited_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.violations.open_count', 0)
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', false);
    }

    public function test_fresh_extension_heartbeat_without_content_script_proof_blocks_browsers(): void
    {
        [$student, $studentUser] = $this->makeStudent('restricted_extension_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'label' => 'Chrome browser extension',
            'hostname' => null,
            'platform' => 'chrome_extension',
            'app_version' => '0.1.0',
            'last_seen_at' => now(),
            'last_seen_ip' => '192.168.11.52',
        ]);

        $device->activityEvents()->create([
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'chrome.exe', 'window_title' => 'IXL'],
                ],
            ],
            'observed_at' => now()->subSeconds(61),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.violations.open_count', 1)
            ->assertJsonPath('policy.violations.items.0.rule_title', 'Browser extension removed')
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', true)
            ->assertJsonPath('policy.violation_app_enforcement.browser_reopen_grace_seconds', 0);
    }

    public function test_recent_blocked_extension_visit_prevents_false_restricted_extension_enforcement(): void
    {
        [$student, $studentUser] = $this->makeStudent('active_legacy_extension_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $extensionDevice = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'label' => 'Chrome browser extension',
            'hostname' => null,
            'platform' => 'chrome_extension',
            'app_version' => '0.1.0',
            'last_seen_at' => now(),
            'last_seen_ip' => '192.168.11.52',
        ]);

        BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => $extensionDevice->id,
            'matched_rule_id' => null,
            'mode' => 'whitelist',
            'decision' => 'blocked',
            'url' => 'https://chatgpt.com/',
            'host' => 'chatgpt.com',
            'registrable_domain' => 'chatgpt.com',
            'page_title' => null,
            'meta' => ['source' => 'chrome_extension'],
            'visited_at' => now()->subMinute(),
        ]);

        $device->activityEvents()->create([
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'chrome.exe', 'window_title' => 'Blocked page'],
                ],
            ],
            'observed_at' => now()->subSeconds(61),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.violations.open_count', 0)
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', false);
    }

    public function test_short_browser_extension_outage_does_not_trigger_removal_enforcement(): void
    {
        [$student, $studentUser] = $this->makeStudent('short_extension_outage_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'label' => 'Chrome browser extension',
            'hostname' => null,
            'platform' => 'chrome_extension',
            'app_version' => '0.1.0',
            'last_seen_at' => now()->subMinutes(2),
            'last_seen_ip' => '192.168.11.52',
        ]);

        BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => StudentDevice::query()
                ->where('device_key', 'browser-extension:student:'.$student->id)
                ->value('id'),
            'matched_rule_id' => null,
            'mode' => 'blacklist',
            'decision' => 'allowed',
            'url' => 'https://ixl.com/',
            'host' => 'ixl.com',
            'registrable_domain' => 'ixl.com',
            'page_title' => 'IXL',
            'meta' => ['source' => 'content_script'],
            'visited_at' => now(),
        ]);

        $device->activityEvents()->create([
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'chrome.exe', 'window_title' => 'IXL'],
                ],
            ],
            'observed_at' => now()->subSeconds(61),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.violations.open_count', 0)
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', false);
    }

    public function test_stale_browser_extension_does_not_add_gui_kill_on_top_of_real_violations(): void
    {
        [$student, $studentUser] = $this->makeStudent('stale_extension_plus_violation_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => null,
            'rule_title_snapshot' => 'Recent violation',
            'status' => 'open',
            'penalty_units' => 5,
            'push_up_count' => 5,
            'occurred_at' => now()->subMinutes(20),
        ]);

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'browser-extension:student:'.$student->id,
            'label' => 'Chrome browser extension',
            'hostname' => null,
            'platform' => 'chrome_extension',
            'app_version' => '0.1.0',
            'last_seen_at' => now()->subMinutes(4),
            'last_seen_ip' => '192.168.11.52',
        ]);

        $device->activityEvents()->create([
            'event_type' => 'open_apps',
            'payload' => [
                'apps' => [
                    ['app_name' => 'chrome.exe', 'window_title' => 'IXL'],
                ],
            ],
            'observed_at' => now()->subSeconds(61),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.policy.show'))
            ->assertOk()
            ->assertJsonPath('policy.violations.open_count', 2)
            ->assertJsonPath('policy.violation_app_enforcement.kill_gui_apps', true);
    }

    public function test_device_command_flow_is_scoped_and_result_submission_is_idempotent(): void
    {
        [$student, $studentUser] = $this->makeStudent('command_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $otherDevice = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'other-device',
            'label' => 'Other',
            'platform' => 'windows',
        ]);

        $token = $device->issueToken();
        $otherToken = $otherDevice->issueToken();

        $command = $device->commands()->create([
            'command_type' => 'refresh_policy',
            'status' => 'pending',
            'payload' => ['source' => 'test'],
            'requested_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson(route('api.companion.commands.next'))
            ->assertOk()
            ->assertJsonPath('command.id', $command->id)
            ->assertJsonPath('command.command_type', 'refresh_policy');

        $this->withHeaders($this->authHeaders($otherToken))
            ->postJson(route('api.companion.commands.acknowledge', $command))
            ->assertNotFound();

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.commands.acknowledge', $command))
            ->assertOk()
            ->assertJsonPath('command.status', 'leased');

        $resultPayload = [
            'status' => 'completed',
            'payload' => ['exit_code' => 0],
        ];

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.commands.result', $command), $resultPayload)
            ->assertOk()
            ->assertJsonPath('result.status', 'completed');

        $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.commands.result', $command), $resultPayload)
            ->assertOk()
            ->assertJsonPath('result.status', 'completed');

        $this->assertDatabaseCount('device_command_results', 1);
        $this->assertDatabaseHas('device_commands', [
            'id' => $command->id,
            'status' => 'completed',
        ]);
    }

    public function test_revoked_device_cannot_fetch_policy_and_token_can_be_renewed(): void
    {
        [$student, $studentUser] = $this->makeStudent('revoke_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $renewResponse = $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.token.renew'));

        $renewResponse
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertNotEmpty($renewResponse->json('web.browser_login_url'));

        $newToken = $renewResponse->json('token');

        $this->withHeaders($this->authHeaders($newToken))
            ->postJson(route('api.companion.revoke'))
            ->assertOk()
            ->assertJsonPath('revoked', true);

        $this->withHeaders($this->authHeaders($newToken))
            ->getJson(route('api.companion.policy.show'))
            ->assertForbidden();
    }

    public function test_device_can_request_and_consume_a_browser_login_url(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_login_student', 'secret-pass');
        $device = $this->enrollDevice($studentUser, 'secret-pass');
        $token = $device->issueToken();

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson(route('api.companion.browser-login'));

        $response
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $browserLoginUrl = $response->json('web.browser_login_url');
        $this->assertNotEmpty($browserLoginUrl);

        $this->get($browserLoginUrl)
            ->assertRedirect(route('student.home', absolute: false));

        $this->assertAuthenticatedAs($studentUser);
        $this->assertNotNull($studentUser->fresh()->last_login_at);
    }

    public function test_authenticated_student_browser_session_can_post_attention_events(): void
    {
        [$student, $studentUser] = $this->makeStudent('browser_attention_student', 'secret-pass');

        StudentSetting::create([
            'student_id' => $student->id,
            'push_up_counter' => 0,
            'look_away_event_count' => 0,
            'look_away_event_threshold' => 2,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Tests',
            'summary' => null,
            'instructions' => 'Stay focused.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'created_by_user_id' => $studentUser->id,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Tests',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(2),
            'duration_seconds' => 120,
            'started_by_user_id' => $studentUser->id,
        ]);

        RuleDefinition::create([
            'title' => 'Look away',
            'description' => 'Issued when the student repeatedly looks away during a task.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->postJson(route('student.attention.events.store'), [
                'event_type' => 'look_away',
                'payload' => [
                    'reason' => 'no_face',
                    'score' => 3.7,
                    'away_seconds' => 2.4,
                    'client_event_id' => 'browser-attention-event-1',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('triggered_violation', false)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('reason', 'look_away_recorded');

        $this->actingAs($studentUser)
            ->postJson(route('student.attention.events.store'), [
                'event_type' => 'look_away',
                'payload' => [
                    'reason' => 'no_face',
                    'score' => 3.7,
                    'away_seconds' => 2.4,
                    'client_event_id' => 'browser-attention-event-1',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('reason', 'duplicate_client_event');

        $this->assertSame(0, $student->setting()->first()->look_away_event_count);

        $this->actingAs($studentUser)
            ->postJson(route('student.attention.events.store'), [
                'event_type' => 'look_away',
                'payload' => [
                    'reason' => 'look_away',
                    'score' => 4.1,
                    'away_seconds' => 2.7,
                    'client_event_id' => 'browser-attention-event-2',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('triggered_violation', false)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('reason', 'look_away_recorded');

        $this->assertDatabaseMissing('violations', [
            'student_id' => $student->id,
            'auto_generated_key' => 'look-away:task-session:'.$taskSession->id,
        ]);
        $this->assertSame(2, ActivityLog::query()
            ->where('student_id', $student->id)
            ->where('category', 'attention')
            ->where('action', 'look_away_event')
            ->where('subject_type', TaskSession::class)
            ->where('subject_id', $taskSession->id)
            ->count());
    }

    public function test_authenticated_student_browser_session_can_post_body_missing_attention_event(): void
    {
        config(['services.attention_tracking.body_missing_violation_seconds' => 10]);

        [$student, $studentUser] = $this->makeStudent('browser_body_missing_student', 'secret-pass');

        StudentSetting::create([
            'student_id' => $student->id,
            'push_up_counter' => 0,
            'look_away_event_count' => 0,
            'look_away_event_threshold' => 2,
        ]);

        $taskTemplate = TaskTemplate::create([
            'title' => 'Coding',
            'summary' => null,
            'instructions' => 'Stay visible.',
            'default_duration_minutes' => 30,
            'requires_internet' => false,
            'created_by_user_id' => $studentUser->id,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'task_template_id' => $taskTemplate->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(2),
            'duration_seconds' => 120,
            'started_by_user_id' => $studentUser->id,
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Left camera view',
            'description' => 'Issued when the student leaves the camera view during a task.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)
            ->postJson(route('student.attention.events.store'), [
                'event_type' => 'body_missing',
                'payload' => [
                    'reason' => 'body_missing',
                    'score' => 0,
                    'body_confidence' => 0,
                    'away_seconds' => 10.5,
                    'client_event_id' => 'browser-body-missing-event-1',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('triggered_violation', true)
            ->assertJsonPath('reason', 'violation_created');

        $this->actingAs($studentUser)
            ->postJson(route('student.attention.events.store'), [
                'event_type' => 'body_missing',
                'payload' => [
                    'reason' => 'body_missing',
                    'score' => 0,
                    'body_confidence' => 0,
                    'away_seconds' => 12,
                    'client_event_id' => 'browser-body-missing-event-1',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('reason', 'duplicate_client_event');

        $this->assertDatabaseHas('violations', [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Left camera view',
            'auto_generated_key' => 'body-missing:task-session:'.$taskSession->id,
        ]);
    }

    private function makeStudent(string $username, string $password): array
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => $username,
            'password' => Hash::make($password),
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => ucfirst($username),
            'status' => 'active',
            'notes' => null,
        ]);

        return [$student, $studentUser];
    }

    private function enrollDevice(User $studentUser, string $password): StudentDevice
    {
        $this->postJson(route('api.companion.enroll'), [
            'username' => $studentUser->username,
            'password' => $password,
            'device_key' => 'device-'.strtolower($studentUser->username),
            'label' => 'Desk PC',
            'hostname' => 'desk-pc',
            'platform' => 'windows',
            'app_version' => '0.1.0',
        ])->assertOk();

        return StudentDevice::query()->where('device_key', 'device-'.strtolower($studentUser->username))->firstOrFail();
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
