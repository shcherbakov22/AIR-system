<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentMonitorCapture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentMonitorCaptureHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_same_day_capture_history_for_a_capture(): void
    {
        Carbon::setTestNow('2026-03-14 12:00:00');

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

        StudentMonitorCapture::create([
            'student_id' => $student->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => 'captures/old-day.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'captured_at' => Carbon::parse('2026-03-13 20:00:00'),
            'uploaded_at' => Carbon::parse('2026-03-13 20:00:00'),
            'task_title_snapshot' => 'Old Day',
            'source_label' => 'Legacy uploader',
        ]);

        $older = StudentMonitorCapture::create([
            'student_id' => $student->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => 'captures/older.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'captured_at' => Carbon::parse('2026-03-14 08:00:00'),
            'uploaded_at' => Carbon::parse('2026-03-14 08:00:00'),
            'task_title_snapshot' => 'Reading',
            'source_label' => 'Legacy uploader',
        ]);

        $newer = StudentMonitorCapture::create([
            'student_id' => $student->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => 'captures/newer.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'captured_at' => Carbon::parse('2026-03-14 10:00:00'),
            'uploaded_at' => Carbon::parse('2026-03-14 10:00:00'),
            'task_title_snapshot' => 'Coding',
            'source_label' => 'Legacy uploader',
        ]);

        StudentMonitorCapture::create([
            'student_id' => $student->id,
            'capture_kind' => 'camera',
            'disk' => 'local',
            'path' => 'captures/camera.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'captured_at' => Carbon::parse('2026-03-14 11:00:00'),
            'uploaded_at' => Carbon::parse('2026-03-14 11:00:00'),
            'task_title_snapshot' => 'Camera',
            'source_label' => 'Legacy uploader',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.student-monitor-captures.day-history', $older))
            ->assertOk()
            ->assertJsonCount(2, 'captures')
            ->assertJsonPath('captures.0.id', $newer->id)
            ->assertJsonPath('captures.1.id', $older->id);

        Carbon::setTestNow();
    }

    public function test_admin_capture_history_includes_same_day_rows_that_only_have_uploaded_at(): void
    {
        Carbon::setTestNow('2026-03-14 12:00:00');

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

        $uploadedOnlyOlder = StudentMonitorCapture::create([
            'student_id' => $student->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => 'captures/uploaded-only-older.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'captured_at' => null,
            'uploaded_at' => Carbon::parse('2026-03-14 08:00:00'),
            'task_title_snapshot' => 'Uploaded Older',
            'source_label' => 'Legacy uploader',
        ]);

        $uploadedOnlyNewer = StudentMonitorCapture::create([
            'student_id' => $student->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => 'captures/uploaded-only-newer.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'captured_at' => null,
            'uploaded_at' => Carbon::parse('2026-03-14 10:00:00'),
            'task_title_snapshot' => 'Uploaded Newer',
            'source_label' => 'Legacy uploader',
        ]);

        StudentMonitorCapture::create([
            'student_id' => $student->id,
            'capture_kind' => 'screen',
            'disk' => 'local',
            'path' => 'captures/old-day-uploaded-only.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'captured_at' => null,
            'uploaded_at' => Carbon::parse('2026-03-13 20:00:00'),
            'task_title_snapshot' => 'Old Day',
            'source_label' => 'Legacy uploader',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.student-monitor-captures.day-history', $uploadedOnlyOlder))
            ->assertOk()
            ->assertJsonCount(2, 'captures')
            ->assertJsonPath('captures.0.id', $uploadedOnlyNewer->id)
            ->assertJsonPath('captures.1.id', $uploadedOnlyOlder->id);

        Carbon::setTestNow();
    }
}
