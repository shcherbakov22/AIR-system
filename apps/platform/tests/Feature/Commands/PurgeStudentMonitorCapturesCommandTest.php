<?php

namespace Tests\Feature\Commands;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentMonitorCapture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeStudentMonitorCapturesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_deletes_captures_before_today_and_keeps_todays_files(): void
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

        Storage::disk('local')->put('captures/yesterday.jpg', 'old');
        Storage::disk('local')->put('captures/today.jpg', 'new');

        $oldCapture = StudentMonitorCapture::create([
            'student_id' => $student->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => 'captures/yesterday.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 3,
            'captured_at' => now()->subDay()->startOfDay(),
            'uploaded_at' => now()->subDay()->startOfDay(),
            'task_title_snapshot' => 'Old',
            'source_label' => 'Legacy uploader',
        ]);

        $todayCapture = StudentMonitorCapture::create([
            'student_id' => $student->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => 'captures/today.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 3,
            'captured_at' => now(),
            'uploaded_at' => now(),
            'task_title_snapshot' => 'New',
            'source_label' => 'Legacy uploader',
        ]);

        Artisan::call('monitor:purge-student-captures');

        $this->assertDatabaseMissing('student_monitor_captures', [
            'id' => $oldCapture->id,
        ]);

        $this->assertDatabaseHas('student_monitor_captures', [
            'id' => $todayCapture->id,
        ]);

        Storage::disk('local')->assertMissing('captures/yesterday.jpg');
        Storage::disk('local')->assertExists('captures/today.jpg');
    }
}
