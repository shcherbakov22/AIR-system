<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyCaptureCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeJpeg(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEBAVFRUVFRUVFRUVFRUVFRUVFRUXFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGhAQGi0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAADAQAAAAAAAAAAAAAAAAAAAQID/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEAMQAAAB6AAAAP/EABQQAQAAAAAAAAAAAAAAAAAAADD/2gAIAQEAAT8Af//EABQRAQAAAAAAAAAAAAAAAAAAADD/2gAIAQIBAT8Af//EABQRAQAAAAAAAAAAAAAAAAAAADD/2gAIAQMBAT8Af//Z'),
        );
    }

    public function test_legacy_check_endpoint_uses_student_credentials(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
            'password' => Hash::make('0'),
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 40,
            'started_at' => now()->subMinutes(5),
            'duration_seconds' => 0,
            'started_by_user_id' => $user->id,
        ]);

        $response = $this->get('/ss/upl1.php?name=ego&pass=0');

        $response->assertOk();
        $this->assertStringEndsWith(':OK', $response->getContent());
    }

    public function test_legacy_screen_upload_accepts_filename_and_password_auth(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'ego',
            'password' => Hash::make('0'),
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Coding',
            'planned_duration_minutes' => 50,
            'started_at' => now()->subMinutes(2),
            'duration_seconds' => 0,
            'started_by_user_id' => $user->id,
        ]);

        $response = $this->post('/ss/uplscr.php', [
            'username' => 'ego',
            'pass' => '0',
            'filename' => $this->fakeJpeg('screen.jpg'),
        ]);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $this->assertDatabaseHas('student_monitor_captures', [
            'student_id' => $student->id,
            'capture_kind' => 'screen',
            'task_title_snapshot' => 'Coding',
            'source_label' => 'Legacy uploader',
        ]);
    }
}
