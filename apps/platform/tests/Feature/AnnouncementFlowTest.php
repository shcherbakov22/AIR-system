<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\DeviceCommand;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentor_can_send_announcement_with_attachment(): void
    {
        Storage::fake('local');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        $file = UploadedFile::fake()->create('notice.png', 120, 'image/png');

        $this->actingAs($mentor)
            ->post(route('admin.announcements.store'), [
                'body' => 'Read https://example.com/update',
                'attachment' => $file,
            ])
            ->assertRedirect(route('admin.announcements.index', absolute: false))
            ->assertSessionHas('success', 'Announcement sent.');

        /** @var ChatMessage $message */
        $message = ChatMessage::query()->sole();

        $this->assertSame('announcement', $message->channel);
        $this->assertSame($mentor->id, $message->sender_user_id);
        $this->assertNull($message->student_id);
        $this->assertSame('Read https://example.com/update', $message->body);
        Storage::disk('local')->assertExists($message->attachment_path);
    }

    public function test_mentor_announcement_queues_visible_device_message_for_active_student_devices(): void
    {
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $activeStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
        ]);

        $activeStudent = Student::create([
            'user_id' => $activeStudentUser->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        $inactiveStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'old',
        ]);

        $inactiveStudent = Student::create([
            'user_id' => $inactiveStudentUser->id,
            'display_name' => 'Old',
            'status' => 'inactive',
            'notes' => null,
        ]);

        $activeDevice = StudentDevice::create([
            'student_id' => $activeStudent->id,
            'device_key' => 'announcement-active-device',
            'label' => 'Desk PC',
            'platform' => 'windows',
        ]);

        StudentDevice::create([
            'student_id' => $activeStudent->id,
            'device_key' => 'announcement-revoked-device',
            'label' => 'Old PC',
            'platform' => 'windows',
            'revoked_at' => now(),
        ]);

        StudentDevice::create([
            'student_id' => $inactiveStudent->id,
            'device_key' => 'announcement-inactive-device',
            'label' => 'Inactive PC',
            'platform' => 'windows',
        ]);

        $this->actingAs($mentor)
            ->post(route('admin.announcements.store'), [
                'body' => 'Class starts in five minutes.',
            ])
            ->assertRedirect(route('admin.announcements.index', absolute: false));

        $message = ChatMessage::query()->sole();
        $command = DeviceCommand::query()->sole();

        $this->assertSame($activeDevice->id, $command->student_device_id);
        $this->assertSame('show_message', $command->command_type);
        $this->assertSame('pending', $command->status);
        $this->assertSame([
            'title' => 'Announcement',
            'body' => 'Class starts in five minutes.',
            'display_seconds' => 20,
            'chat_message_id' => $message->id,
        ], $command->payload);
    }

    public function test_student_can_view_read_only_announcements(): void
    {
        $mentor = User::factory()->create([
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

        ChatMessage::create([
            'student_id' => null,
            'sender_user_id' => $mentor->id,
            'channel' => 'announcement',
            'body' => 'This is read only.',
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.announcements.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Announcements/Show')
                ->where('announcementThread.messages.0.body', 'This is read only.')
                ->where('announcementThread.messages.0.sent_by_role', 'mentor')
            );
    }

    public function test_all_students_see_the_same_global_announcement_thread(): void
    {
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $firstStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
        ]);

        Student::create([
            'user_id' => $firstStudentUser->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        $secondStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'dim',
        ]);

        Student::create([
            'user_id' => $secondStudentUser->id,
            'display_name' => 'Dim',
            'status' => 'active',
            'notes' => null,
        ]);

        ChatMessage::create([
            'student_id' => null,
            'sender_user_id' => $mentor->id,
            'channel' => 'announcement',
            'body' => 'Shared with everyone.',
        ]);

        $this->actingAs($firstStudentUser)
            ->get(route('student.announcements.show'))
            ->assertInertia(fn ($page) => $page
                ->where('announcementThread.messages.0.body', 'Shared with everyone.')
            );

        $this->actingAs($secondStudentUser)
            ->get(route('student.announcements.show'))
            ->assertInertia(fn ($page) => $page
                ->where('announcementThread.messages.0.body', 'Shared with everyone.')
            );
    }

    public function test_student_cannot_post_to_global_announcement_thread(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        $this->actingAs($studentUser)
            ->post(route('admin.announcements.store'), [
                'body' => 'I should not be able to do this.',
            ])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_opening_announcements_marks_global_announcements_as_seen(): void
    {
        $mentor = User::factory()->create([
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

        ChatMessage::create([
            'student_id' => null,
            'sender_user_id' => $mentor->id,
            'channel' => 'announcement',
            'body' => 'Everyone should read this.',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $this->assertNull($student->last_seen_announcements_at);

        $this->actingAs($studentUser)
            ->get(route('student.announcements.show'))
            ->assertOk();

        $student->refresh();

        $this->assertNotNull($student->last_seen_announcements_at);
    }

    public function test_mentor_can_delete_announcement_messages(): void
    {
        Storage::fake('local');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $path = UploadedFile::fake()->create('notice.png', 120, 'image/png')->store('announcements/global', 'local');

        $message = ChatMessage::create([
            'student_id' => null,
            'sender_user_id' => $mentor->id,
            'channel' => 'announcement',
            'body' => 'Delete announcement',
            'attachment_disk' => 'local',
            'attachment_path' => $path,
            'attachment_name' => 'notice.png',
            'attachment_mime' => 'image/png',
            'attachment_size' => 1234,
        ]);

        $this->actingAs($mentor)
            ->delete(route('admin.announcements.destroy', $message))
            ->assertRedirect(route('admin.announcements.index', absolute: false))
            ->assertSessionHas('success', 'Announcement deleted.');

        $this->assertDatabaseMissing('chat_messages', [
            'id' => $message->id,
        ]);
        Storage::disk('local')->assertMissing($path);
    }
}
