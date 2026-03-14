<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\SpeechAnnouncement;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SpeechAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_next_unspoken_announcement_without_marking_it_spoken(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

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

        $first = SpeechAnnouncement::create([
            'student_id' => $student->id,
            'kind' => 'violation',
            'message' => 'Ego got a Observe the time violation.',
        ]);

        SpeechAnnouncement::create([
            'student_id' => $student->id,
            'kind' => 'task_finished',
            'message' => 'Ego finished Coding in 24 minutes.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.speech-announcements.next'))
            ->assertOk()
            ->assertJson([
                'announcement' => [
                    'id' => $first->id,
                    'kind' => 'violation',
                    'message' => 'Ego got a Observe the time violation.',
                ],
            ]);

        $this->assertDatabaseHas('speech_announcements', [
            'id' => $first->id,
            'spoken_at' => null,
        ]);
    }

    public function test_admin_gets_null_when_no_pending_announcements_exist(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.speech-announcements.next'))
            ->assertOk()
            ->assertExactJson([
                'announcement' => null,
            ]);
    }

    public function test_admin_can_fetch_spoken_announcement_history(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'dima',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Dima',
            'status' => 'active',
            'notes' => null,
        ]);

        $older = SpeechAnnouncement::create([
            'student_id' => $student->id,
            'kind' => 'violation',
            'message' => 'Dima got a Observe the time violation.',
            'spoken_at' => now()->subMinutes(5),
        ]);

        $newer = SpeechAnnouncement::create([
            'student_id' => $student->id,
            'kind' => 'task_finished',
            'message' => 'Dima finished Coding in 24 minutes.',
            'spoken_at' => now()->subMinute(),
        ]);

        SpeechAnnouncement::create([
            'student_id' => $student->id,
            'kind' => 'task_finished',
            'message' => 'Pending item should not appear.',
            'spoken_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.speech-announcements.history'))
            ->assertOk()
            ->assertJsonCount(2, 'announcements')
            ->assertJsonPath('announcements.0.id', $newer->id)
            ->assertJsonPath('announcements.0.message', 'Dima finished Coding in 24 minutes.')
            ->assertJsonPath('announcements.0.student_name', 'Dima')
            ->assertJsonPath('announcements.1.id', $older->id);
    }

    public function test_speech_worker_command_marks_announcement_spoken_after_successful_playback(): void
    {
        Process::fake();

        $announcement = SpeechAnnouncement::create([
            'kind' => 'violation',
            'message' => 'Ego got a Observe the time violation.',
        ]);

        $this->artisan('speech:play-announcements --once')
            ->assertExitCode(0);

        $announcement->refresh();

        $this->assertNotNull($announcement->spoken_at);
        $this->assertNull($announcement->processing_started_at);
        $this->assertNull($announcement->processing_host);
    }

    public function test_admin_can_toggle_server_speech_state(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.speech-announcements.state.update'), [
                'enabled' => false,
            ])
            ->assertOk()
            ->assertJson([
                'enabled' => false,
            ]);

        $this->assertSame('0', AppSetting::getValue('server_speech_enabled'));
    }

    public function test_speech_worker_leaves_announcements_pending_when_server_speech_is_disabled(): void
    {
        Process::fake();
        AppSetting::putBoolean('server_speech_enabled', false);

        $announcement = SpeechAnnouncement::create([
            'kind' => 'violation',
            'message' => 'Voice should stay paused.',
        ]);

        $this->artisan('speech:play-announcements --once')
            ->assertExitCode(0);

        $announcement->refresh();

        $this->assertNull($announcement->spoken_at);
        $this->assertNull($announcement->processing_started_at);
        $this->assertNull($announcement->processing_host);
    }
}
