<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\ScheduleWeekday;
use App\Models\AiOverseerDecision;
use App\Models\RuleDefinition;
use App\Models\ScheduleRun;
use App\Models\ScheduleRunBlock;
use App\Models\ScheduleTemplate;
use App\Models\Student;
use App\Models\StudentAppPolicy;
use App\Models\StudentDevice;
use App\Models\StudentDeviceInstalledApp;
use App\Models\StudentMonitorCapture;
use App\Models\TaskSession;
use App\Models\Violation;
use App\Models\User;
use App\Models\DeviceActivityEvent;
use App\Models\ChatMessage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_are_sent_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_dashboard',
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('serverNow')
                ->where('serverSpeech.enabled', true)
                ->has('monitorStudents', 0)
            );
    }

    public function test_admin_database_route_renders_adminer_auto_login_bridge(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_database',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.database'))
            ->assertOk()
            ->assertSee('Opening database', false)
            ->assertSee('name="auth[driver]"', false)
            ->assertSee('name="auth[server]"', false)
            ->assertSee('name="auth[username]"', false)
            ->assertSee('name="auth[password]"', false)
            ->assertSee('name="auth[db]"', false)
            ->assertSee('fetch(\'/adminer.php\'', false);
    }

    public function test_admin_dashboard_shows_active_student_task_sessions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_activity',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_activity',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Activity Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 40,
            'started_at' => now()->subMinutes(12),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('monitorStudents', 1)
                ->where('monitorStudents.0.display_name', 'Activity Student')
                ->where('monitorStudents.0.active_task_session.task_title', 'Reading')
                ->where('monitorStudents.0.active_task_session.planned_duration_minutes', 40)
                ->where('monitorStudents.0.active_task_session.unfinished_url', route('admin.task-sessions.unfinished', $taskSession))
            );
    }

    public function test_admin_dashboard_shows_active_schedule_blocks_and_time_spent(): void
    {
        Carbon::setTestNow('2026-03-12 15:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_schedule_monitor',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_schedule_monitor',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Schedule Monitor Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $scheduleRun = ScheduleRun::create([
            'student_id' => $student->id,
            'status' => 'active',
            'schedule_name_snapshot' => 'Afternoon Focus',
            'schedule_weekday_snapshot' => 'Thursday',
            'started_at' => Carbon::parse('2026-03-12 13:00:00'),
            'started_by_user_id' => $studentUser->id,
        ]);

        $completedBlock = ScheduleRunBlock::create([
            'schedule_run_id' => $scheduleRun->id,
            'position' => 1,
            'status' => 'completed',
            'start_time_snapshot' => '13:00',
            'duration_minutes_snapshot' => 30,
            'task_title_snapshot' => 'Reading',
            'started_at' => Carbon::parse('2026-03-12 13:00:00'),
            'completed_at' => Carbon::parse('2026-03-12 13:30:00'),
        ]);

        $activeBlock = ScheduleRunBlock::create([
            'schedule_run_id' => $scheduleRun->id,
            'position' => 2,
            'status' => 'in_progress',
            'start_time_snapshot' => '13:30',
            'duration_minutes_snapshot' => 40,
            'task_title_snapshot' => 'Coding',
            'started_at' => Carbon::parse('2026-03-12 14:35:00'),
        ]);

        $completedTaskSession = TaskSession::create([
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $completedBlock->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 30,
            'started_at' => Carbon::parse('2026-03-12 13:00:00'),
            'ended_at' => Carbon::parse('2026-03-12 13:30:00'),
            'duration_seconds' => 1800,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $activeTaskSession = TaskSession::create([
            'student_id' => $student->id,
            'schedule_run_id' => $scheduleRun->id,
            'schedule_run_block_id' => $activeBlock->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 40,
            'started_at' => Carbon::parse('2026-03-12 14:35:00'),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('monitorStudents', 1)
                ->where('monitorStudents.0.display_name', 'Schedule Monitor Student')
                ->where('monitorStudents.0.active_schedule_run.name', 'Afternoon Focus')
                ->where('monitorStudents.0.schedule_board.name', 'Afternoon Focus')
                ->where('monitorStudents.0.schedule_board.source_label', 'Active run')
                ->where('monitorStudents.0.active_schedule_run.completed_blocks', 1)
                ->where('monitorStudents.0.active_schedule_run.total_blocks', 2)
                ->where('monitorStudents.0.active_schedule_run.blocks.0.task_title', 'Reading')
                ->where('monitorStudents.0.active_schedule_run.blocks.0.actual_duration_seconds', 1800)
                ->where('monitorStudents.0.active_schedule_run.blocks.0.unfinished_url', route('admin.task-sessions.unfinished', $completedTaskSession))
                ->where('monitorStudents.0.active_schedule_run.blocks.1.task_title', 'Coding')
                ->where('monitorStudents.0.active_schedule_run.blocks.1.actual_duration_seconds', 1500)
                ->where('monitorStudents.0.active_schedule_run.blocks.1.unfinished_url', route('admin.task-sessions.unfinished', $activeTaskSession))
                ->where('monitorStudents.0.schedule_board.blocks.0.display_duration_caption', 'Spent')
                ->where('monitorStudents.0.schedule_board.blocks.1.display_duration_caption', 'Spent')
            );

        Carbon::setTestNow();
    }

    public function test_admin_dashboard_falls_back_to_saved_schedule_templates(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_saved_schedule_board',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_saved_schedule_board',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Saved Schedule Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $student->id,
            'name' => 'Evening Flow',
            'weekday' => ScheduleWeekday::Wednesday,
            'notes' => null,
            'created_by_user_id' => $admin->id,
        ]);

        $scheduleTemplate->entries()->createMany([
            [
                'task_template_id' => null,
                'task_title' => 'Reading',
                'task_summary' => null,
                'task_instructions' => null,
                'position' => 1,
                'start_time' => '18:00',
                'duration_minutes' => 25,
                'notes' => null,
            ],
            [
                'task_template_id' => null,
                'task_title' => 'Coding',
                'task_summary' => null,
                'task_instructions' => null,
                'position' => 2,
                'start_time' => '18:25',
                'duration_minutes' => 40,
                'notes' => null,
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('monitorStudents', 1)
                ->where('monitorStudents.0.display_name', 'Saved Schedule Student')
                ->where('monitorStudents.0.schedule_board.name', 'Evening Flow')
                ->where('monitorStudents.0.schedule_board.source_type', 'template')
                ->where('monitorStudents.0.schedule_board.source_label', 'Saved schedule')
                ->where('monitorStudents.0.schedule_board.total_blocks', 2)
                ->where('monitorStudents.0.schedule_board.blocks.0.task_title', 'Reading')
                ->where('monitorStudents.0.schedule_board.blocks.0.display_duration_caption', 'Planned')
                ->where('monitorStudents.0.schedule_board.blocks.1.task_title', 'Coding')
            );
    }

    public function test_admin_dashboard_shows_latest_monitor_captures_and_open_violations(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_monitor',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_monitor',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Monitor Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $taskSession = TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 55,
            'started_at' => now()->subMinutes(5),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $screenCapture = StudentMonitorCapture::create([
            'student_id' => $student->id,
            'task_session_id' => $taskSession->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => 'student-monitor-captures/student_monitor/screen/example.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'captured_at' => now()->subMinute(),
            'uploaded_at' => now()->subMinute(),
            'task_title_snapshot' => 'Coding',
            'source_label' => 'Browser Extension',
            'source_version' => '1.0.0',
            'meta' => [],
        ]);

        StudentMonitorCapture::create([
            'student_id' => $student->id,
            'task_session_id' => $taskSession->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => '0',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'captured_at' => now(),
            'uploaded_at' => now(),
            'task_title_snapshot' => 'Broken screen row',
            'source_label' => 'Browser Extension',
            'source_version' => '1.0.0',
            'meta' => [],
        ]);

        $device = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'dashboard-device',
            'label' => 'Desk PC',
            'platform' => 'windows',
            'last_seen_at' => now(),
        ]);

        DeviceActivityEvent::create([
            'student_device_id' => $device->id,
            'event_type' => 'focused_app',
            'app_name' => 'Code.exe',
            'window_title' => 'AIR System - Dashboard',
            'browser_domain' => 'github.com',
            'payload' => [],
            'observed_at' => now()->subSeconds(15),
        ]);

        DeviceActivityEvent::create([
            'student_device_id' => $device->id,
            'event_type' => 'open_apps',
            'app_name' => 'Code.exe',
            'window_title' => 'AIR System - Dashboard',
            'browser_domain' => 'github.com',
            'payload' => [
                'apps' => [
                    [
                        'app_name' => 'Code.exe',
                        'window_title' => 'AIR System - Dashboard',
                    ],
                    [
                        'app_name' => 'chrome.exe',
                        'window_title' => 'GitHub',
                    ],
                ],
            ],
            'observed_at' => now()->subSeconds(10),
        ]);

        StudentAppPolicy::create([
            'student_id' => $student->id,
            'app_key' => 'code.exe',
            'app_name' => 'Code.exe',
            'status' => 'permitted',
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now()->subMinute(),
        ]);

        StudentAppPolicy::create([
            'student_id' => $student->id,
            'app_key' => 'steam.exe',
            'app_name' => 'Steam.exe',
            'status' => 'pending_review',
            'first_seen_at' => now()->subMinutes(2),
            'last_seen_at' => now()->subMinutes(2),
            'grace_deadline_at' => now()->addMinute(),
        ]);

        StudentAppPolicy::create([
            'student_id' => $student->id,
            'app_key' => 'game.exe',
            'app_name' => 'Game.exe',
            'status' => 'blocked',
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
        ]);

        StudentDeviceInstalledApp::create([
            'student_device_id' => $device->id,
            'app_key' => 'code.exe',
            'display_name' => 'Visual Studio Code',
            'display_version' => '1.2.3',
            'publisher' => 'Microsoft',
            'install_location' => 'C:\\Program Files\\VS Code',
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now()->subMinute(),
            'meta' => ['source' => 'registry_uninstall'],
        ]);

        $cameraCapture = StudentMonitorCapture::create([
            'student_id' => $student->id,
            'task_session_id' => $taskSession->id,
            'capture_kind' => 'camera',
            'disk' => 'local',
            'path' => 'student-monitor-captures/student_monitor/camera/example.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'captured_at' => now()->subSeconds(30),
            'uploaded_at' => now()->subSeconds(30),
            'task_title_snapshot' => 'Coding',
            'source_label' => 'Hardware Bridge',
            'source_version' => '1.0.0',
            'meta' => [],
        ]);

        StudentMonitorCapture::create([
            'student_id' => $student->id,
            'task_session_id' => $taskSession->id,
            'capture_kind' => 'camera',
            'disk' => 'local',
            'path' => '0',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'captured_at' => now(),
            'uploaded_at' => now(),
            'task_title_snapshot' => 'Broken camera row',
            'source_label' => 'Hardware Bridge',
            'source_version' => '1.0.0',
            'meta' => [],
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Observe the time',
            'description' => null,
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        Violation::create([
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'status' => 'open',
            'rule_title_snapshot' => $ruleDefinition->title,
            'penalty_units' => 10,
            'occurred_at' => now()->subMinute(),
            'notes' => 'Automatic violation for opening blocked program Steam.exe during task Coding.',
            'reported_by_user_id' => $admin->id,
        ]);

        ChatMessage::create([
            'student_id' => $student->id,
            'sender_user_id' => $studentUser->id,
            'channel' => 'chat',
            'body' => 'Please check this first.',
            'created_at' => now()->subSeconds(45),
            'updated_at' => now()->subSeconds(45),
        ]);

        AiOverseerDecision::create([
            'student_id' => $student->id,
            'requested_by_user_id' => $studentUser->id,
            'request_type' => 'remove_violation',
            'status' => 'mentor_review',
            'decision' => 'remove_violation',
            'confidence' => 85,
            'student_reason' => 'Please review this.',
            'student_message' => 'I sent this to your mentor for review.',
            'mentor_summary' => 'Student asked to remove a violation.',
            'reason' => 'Needs mentor decision.',
            'context_snapshot' => [],
            'raw_response' => [],
            'model' => 'openai/gpt-oss-120b',
            'prompt_version' => 'ai-overseer-v1',
            'decided_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('monitorStudents', 1)
                ->where('monitorStudents.0.display_name', 'Monitor Student')
                ->where('monitorStudents.0.latest_screen_capture.id', $screenCapture->id)
                ->where('monitorStudents.0.latest_screen_capture.task_title', 'Coding')
                ->where('monitorStudents.0.latest_camera_capture.id', $cameraCapture->id)
                ->where('monitorStudents.0.latest_camera_capture.source_label', 'Hardware Bridge')
                ->where('monitorStudents.0.latest_device_activity.device_label', 'Desk PC')
                ->where('monitorStudents.0.latest_device_activity.focused_app.app_name', 'Code.exe')
                ->where('monitorStudents.0.latest_device_activity.open_apps.1.app_name', 'chrome.exe')
                ->where('monitorStudents.0.latest_device_activity.installed_apps.0.display_name', 'Visual Studio Code')
                ->where('monitorStudents.0.app_control.pending_review.0.app_name', 'Steam.exe')
                ->where('monitorStudents.0.ai_overseer_notifications.count', 1)
                ->where('monitorStudents.0.ai_overseer_notifications.items.0.request_type', 'remove_violation')
                ->where('monitorStudents.0.ai_overseer_notifications.items.0.confidence', 85)
                ->where('monitorStudents.0.app_control.permitted.0.app_name', 'Code.exe')
                ->where('monitorStudents.0.app_control.blocked.0.app_name', 'Game.exe')
                ->where('monitorStudents.0.app_control.permit_url_template', route('admin.students.app-policies.permit', [$student, '__APP_POLICY__']))
                ->where('monitorStudents.0.app_control.block_url_template', route('admin.students.app-policies.block', [$student, '__APP_POLICY__']))
                ->where('monitorStudents.0.communication_gate.has_unread_student_chat', true)
                ->where('monitorStudents.0.communication_gate.unread_student_chat.body', 'Please check this first.')
                ->where('monitorStudents.0.communication_gate.chat_url', route('admin.chats.show', $student))
                ->where('monitorStudents.0.open_violations.0.rule_title', 'Observe the time')
                ->where('monitorStudents.0.open_violations.0.push_up_count', 10)
                ->where('monitorStudents.0.open_violations.0.notes', 'Automatic violation for opening blocked program Steam.exe during task Coding.')
                ->where('monitorStudents.0.current_push_up_count', 10)
                ->where('monitorStudents.0.violation_rule_options.0.title', 'Observe the time')
            );
    }

    public function test_admin_dashboard_keeps_students_in_alphabetical_order(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_dashboard_alpha_order',
        ]);

        $zetaUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_dashboard_zeta',
        ]);

        $alphaUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_dashboard_alpha',
        ]);

        $zetaStudent = Student::create([
            'user_id' => $zetaUser->id,
            'display_name' => 'Zeta Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $alphaStudent = Student::create([
            'user_id' => $alphaUser->id,
            'display_name' => 'Alpha Student',
            'status' => 'active',
            'notes' => null,
        ]);

        TaskSession::create([
            'student_id' => $zetaStudent->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 30,
            'started_at' => now()->subMinutes(2),
            'duration_seconds' => 0,
            'started_by_user_id' => $zetaUser->id,
        ]);

        StudentMonitorCapture::create([
            'student_id' => $zetaStudent->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => 'student-monitor-captures/dashboard/zeta.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 512,
            'captured_at' => now()->subSeconds(10),
            'uploaded_at' => now()->subSeconds(10),
            'task_title_snapshot' => 'Coding',
            'source_label' => 'AIR Companion',
            'source_version' => '1.0.0',
            'meta' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('monitorStudents', 2)
                ->where('monitorStudents.0.display_name', 'Alpha Student')
                ->where('monitorStudents.1.display_name', 'Zeta Student')
            );
    }

    public function test_student_users_are_sent_to_the_student_home(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_home',
            'name' => 'Student Home User',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Home',
            'status' => 'active',
            'notes' => 'Ready for tasks',
        ]);

        $scheduleTemplate = ScheduleTemplate::create([
            'student_id' => $studentUser->student->id,
            'name' => 'Monday Focus Block',
            'weekday' => ScheduleWeekday::Monday,
            'is_active' => true,
            'notes' => 'Core morning work.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $scheduleTemplate->entries()->create([
            'task_template_id' => null,
            'task_title' => 'Math Review',
            'task_summary' => 'Quick review task.',
            'task_instructions' => 'Complete the review sheet.',
            'position' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'notes' => 'Start with the review sheet.',
        ]);

        $inactiveScheduleTemplate = ScheduleTemplate::create([
            'student_id' => $studentUser->student->id,
            'name' => 'Friday Archive',
            'weekday' => ScheduleWeekday::Friday,
            'is_active' => false,
            'notes' => 'This should stay out of the student portal.',
            'created_by_user_id' => $studentUser->id,
        ]);

        $inactiveScheduleTemplate->entries()->create([
            'task_template_id' => null,
            'task_title' => 'Math Review',
            'task_summary' => 'Quick review task.',
            'task_instructions' => 'Complete the review sheet.',
            'position' => 1,
            'start_time' => '14:00',
            'duration_minutes' => 30,
            'notes' => 'Inactive block note.',
        ]);

        $this->actingAs($studentUser)
            ->get('/dashboard')
            ->assertRedirect(route('student.home', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('student.display_name', 'Student Home')
                ->where('student.status', 'active')
                ->where('student.notes', 'Ready for tasks')
                                ->where('violationSummary.open_violations', 0)
                ->missing('taskAssignments')
                ->where('activeTaskSession', null)
                ->missing('recentTaskSessions')
                ->has('weeklyScheduleTemplates', 1)
                ->where('weeklyScheduleTemplates.0.weekday.label', ScheduleWeekday::Monday->label())
                ->where('weeklyScheduleTemplates.0.entries.0.task.title', 'Math Review')
                ->where('weeklyScheduleTemplates.0.entries.0.start_time', '09:00')
            );
    }

    public function test_student_home_reflects_open_violations_from_admin_logged_violations(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_home_penalties',
            'name' => 'Student Home Penalties',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Student Penalties',
            'status' => 'active',
            'notes' => null,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_home_penalties',
        ]);

        $ruleDefinition = RuleDefinition::create([
            'title' => 'Stay on task',
            'description' => 'Student must stay on the active task.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 0,
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.violations.store'), [
            'student_id' => $student->id,
            'rule_definition_id' => $ruleDefinition->id,
            'occurred_at' => '2026-03-08T12:05',
            'notes' => 'Automatic violation for opening blocked website youtube.com during task Math. URL: https://youtube.com/watch?v=blocked',
        ])->assertRedirect(route('admin.violations.index', absolute: false));

        $this->actingAs($studentUser)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('student.display_name', 'Student Penalties')
                ->where('violationSummary.open_violations', 1)
                ->where('openViolations.0.notes', 'Automatic violation for opening blocked website youtube.com during task Math. URL: https://youtube.com/watch?v=blocked')
            );
    }

    public function test_admin_dashboard_includes_idle_duration_when_student_has_no_active_task(): void
    {
        Carbon::setTestNow('2026-03-12 15:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_idle_duration',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_idle_duration',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Idle Student',
            'status' => 'active',
            'notes' => null,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'status' => 'completed',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 20,
            'started_at' => Carbon::parse('2026-03-12 14:20:00'),
            'ended_at' => Carbon::parse('2026-03-12 14:40:00'),
            'duration_seconds' => 1200,
            'started_by_user_id' => $studentUser->id,
            'stopped_by_user_id' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('monitorStudents', 1)
                ->where('monitorStudents.0.display_name', 'Idle Student')
                ->where('monitorStudents.0.active_task_session', null)
                ->where('monitorStudents.0.idle_for.seconds', 1200)
                ->where('monitorStudents.0.idle_for.label', '20:00')
            );

        Carbon::setTestNow();
    }

    public function test_admin_dashboard_uses_start_of_day_when_student_has_no_task_history(): void
    {
        Carbon::setTestNow('2026-03-12 15:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_idle_start_of_day',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_idle_start_of_day',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Idle Since Morning',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('monitorStudents.0.display_name', 'Idle Since Morning')
                ->where('monitorStudents.0.active_task_session', null)
                ->where('monitorStudents.0.idle_for.seconds', 54000)
                ->where('monitorStudents.0.idle_for.label', '15:00:00')
            );

        Carbon::setTestNow();
    }
}
