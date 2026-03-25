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

class ChatFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentor_can_view_student_chat_list(): void
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
            'student_id' => $student->id,
            'sender_user_id' => $studentUser->id,
            'body' => 'Hello mentor',
        ]);

        $this->actingAs($mentor)
            ->get(route('admin.chats.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Chats/Index')
                ->where('students.0.display_name', 'Ego')
                ->where('students.0.latest_message.body', 'Hello mentor')
            );
    }

    public function test_student_can_send_message_with_attachment_to_mentor_thread(): void
    {
        Storage::fake('local');

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

        $file = UploadedFile::fake()->create('progress.png', 120, 'image/png');

        $this->actingAs($studentUser)
            ->post(route('student.chat.store'), [
                'body' => 'See https://example.com',
                'attachment' => $file,
            ])
            ->assertRedirect(route('student.chat.show', absolute: false))
            ->assertSessionHas('success', 'Message sent.');

        /** @var ChatMessage $message */
        $message = ChatMessage::query()->sole();

        $this->assertSame($student->id, $message->student_id);
        $this->assertSame($studentUser->id, $message->sender_user_id);
        $this->assertSame('See https://example.com', $message->body);
        $this->assertTrue($message->isImage());
        Storage::disk('local')->assertExists($message->attachment_path);
    }

    public function test_student_can_only_view_their_own_chat_thread(): void
    {
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $firstStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
        ]);

        $secondStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'dima',
        ]);

        $firstStudent = Student::create([
            'user_id' => $firstStudentUser->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        $secondStudent = Student::create([
            'user_id' => $secondStudentUser->id,
            'display_name' => 'Dima',
            'status' => 'active',
            'notes' => null,
        ]);

        ChatMessage::create([
            'student_id' => $firstStudent->id,
            'sender_user_id' => $mentor->id,
            'body' => 'Private thread',
        ]);

        $this->actingAs($firstStudentUser)
            ->get(route('student.chat.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Chat/Show')
                ->where('studentThread.student.display_name', 'Ego')
            );

        $this->actingAs($secondStudentUser)
            ->get(route('admin.chats.show', $firstStudent))
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_chat_attachment_is_visible_only_to_mentor_or_owning_student(): void
    {
        Storage::fake('local');

        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
        ]);

        $otherStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'dima',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        Student::create([
            'user_id' => $otherStudentUser->id,
            'display_name' => 'Dima',
            'status' => 'active',
            'notes' => null,
        ]);

        $path = UploadedFile::fake()->create('proof.png', 120, 'image/png')->store("chat/{$student->id}", 'local');

        $message = ChatMessage::create([
            'student_id' => $student->id,
            'sender_user_id' => $mentor->id,
            'body' => 'Image attached',
            'attachment_disk' => 'local',
            'attachment_path' => $path,
            'attachment_name' => 'proof.png',
            'attachment_mime' => 'image/png',
            'attachment_size' => 1234,
        ]);

        $this->actingAs($mentor)
            ->get(route('chat-messages.attachment.show', $message))
            ->assertOk();

        $this->actingAs($studentUser)
            ->get(route('chat-messages.attachment.show', $message))
            ->assertOk();

        $this->actingAs($otherStudentUser)
            ->get(route('chat-messages.attachment.show', $message))
            ->assertNotFound();
    }

    public function test_opening_student_chat_marks_mentor_messages_as_seen(): void
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
            'student_id' => $student->id,
            'sender_user_id' => $mentor->id,
            'channel' => 'chat',
            'body' => 'Read me first.',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $this->assertNull($student->last_seen_mentor_chat_at);

        $this->actingAs($studentUser)
            ->get(route('student.chat.show'))
            ->assertOk();

        $student->refresh();

        $this->assertNotNull($student->last_seen_mentor_chat_at);
    }

    public function test_opening_admin_chat_marks_student_messages_as_seen(): void
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
            'student_id' => $student->id,
            'sender_user_id' => $studentUser->id,
            'channel' => 'chat',
            'body' => 'Need help.',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $this->assertNull($student->last_seen_student_chat_at);

        $this->actingAs($mentor)
            ->get(route('admin.chats.show', $student))
            ->assertOk();

        $student->refresh();

        $this->assertNotNull($student->last_seen_student_chat_at);
    }

    public function test_mentor_can_delete_chat_messages(): void
    {
        Storage::fake('local');

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

        $path = UploadedFile::fake()->create('proof.png', 120, 'image/png')->store("chat/{$student->id}", 'local');

        $message = ChatMessage::create([
            'student_id' => $student->id,
            'sender_user_id' => $mentor->id,
            'channel' => 'chat',
            'body' => 'Delete me',
            'attachment_disk' => 'local',
            'attachment_path' => $path,
            'attachment_name' => 'proof.png',
            'attachment_mime' => 'image/png',
            'attachment_size' => 1234,
        ]);

        $this->actingAs($mentor)
            ->delete(route('admin.chats.destroy', [$student, $message]))
            ->assertRedirect(route('admin.chats.show', $student, absolute: false))
            ->assertSessionHas('success', 'Message deleted.');

        $this->assertDatabaseMissing('chat_messages', [
            'id' => $message->id,
        ]);
        Storage::disk('local')->assertMissing($path);
    }
}
