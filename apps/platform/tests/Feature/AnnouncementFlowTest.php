<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\Student;
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
}
