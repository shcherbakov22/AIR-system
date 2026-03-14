<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\SpeechAnnouncement;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpeechAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_next_unspoken_announcement(): void
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

        $this->assertDatabaseMissing('speech_announcements', [
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
}
