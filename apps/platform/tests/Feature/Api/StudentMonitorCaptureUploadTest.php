<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\TaskSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentMonitorCaptureUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeJpeg(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEBAVFRUVFRUVFRUVFRUVFRUVFRUXFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGhAQGi0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAADAQAAAAAAAAAAAAAAAAAAAQID/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEAMQAAAB6AAAAP/EABQQAQAAAAAAAAAAAAAAAAAAADD/2gAIAQEAAT8Af//EABQRAQAAAAAAAAAAAAAAAAAAADD/2gAIAQIBAT8Af//EABQRAQAAAAAAAAAAAAAAAAAAADD/2gAIAQMBAT8Af//Z'),
        );
    }

    public function test_screen_capture_uploads_for_a_student_and_tracks_the_current_task(): void
    {
        Storage::fake('local');

        config()->set('services.edge_clients.shared_token', 'test-edge-token');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'capture_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Capture Student',
            'status' => 'active',
            'notes' => null,
        ]);

        TaskSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'task_title_snapshot' => 'Reading',
            'planned_duration_minutes' => 40,
            'started_at' => now()->subMinutes(3),
            'duration_seconds' => 0,
            'started_by_user_id' => $studentUser->id,
        ]);

        $response = $this->withHeaders([
            'X-Edge-Client-Token' => 'test-edge-token',
        ])->post(route('api.student-monitor-captures.screen'), [
            'username' => 'capture_student',
            'client_key' => 'capture-client-1',
            'client_type' => 'browser_extension',
            'label' => 'Capture Extension',
            'version' => '0.2.0',
            'capture' => $this->fakeJpeg('screen.jpg'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('capture.capture_kind', 'screen')
            ->assertJsonPath('capture.student.username', 'capture_student')
            ->assertJsonPath('capture.task_title', 'Reading');

        $this->assertDatabaseCount('student_monitor_captures', 1);
        $this->assertDatabaseHas('student_monitor_captures', [
            'student_id' => $student->id,
            'capture_kind' => 'screen',
            'task_title_snapshot' => 'Reading',
        ]);
        $this->assertDatabaseHas('edge_clients', [
            'client_key' => 'capture-client-1',
            'label' => 'Capture Extension',
        ]);

        $storedPath = \App\Models\StudentMonitorCapture::query()->value('path');
        Storage::disk('local')->assertExists($storedPath);
    }

    public function test_camera_capture_accepts_the_legacy_filename_field(): void
    {
        Storage::fake('local');

        config()->set('services.edge_clients.shared_token', 'test-edge-token');

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'legacy_capture_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Legacy Capture Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $response = $this->withHeaders([
            'X-Edge-Client-Token' => 'test-edge-token',
        ])->post(route('api.student-monitor-captures.camera'), [
            'username' => 'legacy_capture_student',
            'filename' => $this->fakeJpeg('camera.jpg'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('capture.capture_kind', 'camera')
            ->assertJsonPath('capture.student.id', $student->id);

        $this->assertDatabaseHas('student_monitor_captures', [
            'student_id' => $student->id,
            'capture_kind' => 'camera',
        ]);
    }
}
