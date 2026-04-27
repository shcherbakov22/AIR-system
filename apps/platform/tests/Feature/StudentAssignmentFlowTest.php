<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DeviceCommand;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\StudentAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentAssignmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_assignment_from_global_page(): void
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

        $this->actingAs($mentor)
            ->post(route('admin.assignments.store'), [
                'student_id' => $student->id,
                'body' => 'Summarize the main ideas.',
            ])
            ->assertRedirect(route('admin.assignments.index', ['student_id' => $student->id], absolute: false))
            ->assertSessionHas('success', 'Assignment created.');

        $this->assertDatabaseHas('student_assignments', [
            'student_id' => $student->id,
            'title' => 'Assignment',
            'body' => 'Summarize the main ideas.',
            'status' => 'unread',
        ]);
    }

    public function test_admin_assignment_queues_visible_device_message_for_active_devices(): void
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

        $activeDevice = StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'assignment-active-device',
            'label' => 'Desk PC',
            'platform' => 'windows',
        ]);

        StudentDevice::create([
            'student_id' => $student->id,
            'device_key' => 'assignment-revoked-device',
            'label' => 'Old PC',
            'platform' => 'windows',
            'revoked_at' => now(),
        ]);

        $this->actingAs($mentor)
            ->post(route('admin.assignments.store'), [
                'student_id' => $student->id,
                'body' => 'Summarize the main ideas.',
            ])
            ->assertRedirect(route('admin.assignments.index', ['student_id' => $student->id], absolute: false));

        $assignment = StudentAssignment::query()->sole();
        $command = DeviceCommand::query()->sole();

        $this->assertSame($activeDevice->id, $command->student_device_id);
        $this->assertSame('show_message', $command->command_type);
        $this->assertSame('pending', $command->status);
        $this->assertSame([
            'title' => 'New assignment',
            'body' => 'Summarize the main ideas.',
            'display_seconds' => 20,
            'student_assignment_id' => $assignment->id,
        ], $command->payload);
    }

    public function test_admin_can_create_assignment_with_drag_dropped_image(): void
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

        $image = $this->fakeImageUpload('worksheet.png');

        $this->actingAs($mentor)
            ->post(route('admin.assignments.store'), [
                'student_id' => $student->id,
                'body' => '',
                'image' => $image,
            ])
            ->assertRedirect(route('admin.assignments.index', ['student_id' => $student->id], absolute: false))
            ->assertSessionHas('success', 'Assignment created.');

        $assignment = StudentAssignment::query()->sole();

        $this->assertSame($student->id, $assignment->student_id);
        $this->assertNull($assignment->body);
        $this->assertSame('local', $assignment->attachment_disk);
        $this->assertSame('worksheet.png', $assignment->attachment_name);
        $this->assertSame('image/png', $assignment->attachment_mime);
        $this->assertNotNull($assignment->attachment_path);
        Storage::disk('local')->assertExists($assignment->attachment_path);
    }

    public function test_student_opening_assignments_marks_unread_items_as_viewed(): void
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

        $assignment = StudentAssignment::create([
            'student_id' => $student->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'Check in',
            'body' => 'Open this page.',
            'status' => 'unread',
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.assignments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Assignments/Index')
                ->where('assignments.0.status', 'viewed')
            );

        $assignment->refresh();
        $this->assertSame('viewed', $assignment->status);
        $this->assertNotNull($assignment->viewed_at);
    }

    public function test_student_can_start_and_hand_in_assignment_and_admin_can_complete_it(): void
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

        $assignment = StudentAssignment::create([
            'student_id' => $student->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'Write summary',
            'status' => 'viewed',
            'viewed_at' => now(),
        ]);

        $this->actingAs($studentUser)
            ->patch(route('student.assignments.start', $assignment))
            ->assertRedirect(route('student.assignments.index', absolute: false))
            ->assertSessionHas('success', 'Assignment marked in progress.');

        $assignment->refresh();
        $this->assertSame('in_progress', $assignment->status);
        $this->assertNotNull($assignment->started_at);

        $this->actingAs($studentUser)
            ->patch(route('student.assignments.hand-in', $assignment))
            ->assertRedirect(route('student.assignments.index', absolute: false))
            ->assertSessionHas('success', 'Assignment handed in.');

        $assignment->refresh();
        $this->assertSame('handed_in', $assignment->status);
        $this->assertNull($assignment->completed_at);

        $this->actingAs($mentor)
            ->patch(route('admin.assignments.complete', $assignment))
            ->assertSessionHas('success', 'Assignment marked completed.');

        $assignment->refresh();
        $this->assertSame('completed', $assignment->status);
        $this->assertNotNull($assignment->completed_at);

        $this->actingAs($mentor)
            ->patch(route('admin.assignments.incomplete', $assignment))
            ->assertSessionHas('success', 'Assignment marked incomplete.');

        $assignment->refresh();
        $this->assertSame('in_progress', $assignment->status);
        $this->assertNull($assignment->completed_at);
        $this->assertNotNull($assignment->started_at);
    }

    public function test_student_assignment_board_exposes_actionable_cards_by_status(): void
    {
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'kanban_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Kanban Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $viewedAssignment = StudentAssignment::create([
            'student_id' => $student->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'Viewed item',
            'status' => 'viewed',
            'viewed_at' => now(),
        ]);

        $inProgressAssignment = StudentAssignment::create([
            'student_id' => $student->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'In progress item',
            'status' => 'in_progress',
            'viewed_at' => now(),
            'started_at' => now(),
        ]);

        $completedAssignment = StudentAssignment::create([
            'student_id' => $student->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'Completed item',
            'status' => 'completed',
            'viewed_at' => now(),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.assignments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Assignments/Index')
                ->has('assignments', 3)
                ->where('assignments.0.id', $viewedAssignment->id)
                ->where('assignments.0.status', 'viewed')
                ->where('assignments.0.start_url', route('student.assignments.start', $viewedAssignment))
                ->where('assignments.0.hand_in_url', null)
                ->where('assignments.1.id', $inProgressAssignment->id)
                ->where('assignments.1.status', 'in_progress')
                ->where('assignments.1.start_url', null)
                ->where('assignments.1.hand_in_url', route('student.assignments.hand-in', $inProgressAssignment))
                ->where('assignments.2.id', $completedAssignment->id)
                ->where('assignments.2.status', 'completed')
                ->where('assignments.2.start_url', null)
                ->where('assignments.2.hand_in_url', null)
            );
    }

    public function test_admin_can_view_single_student_assignments_page(): void
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

        StudentAssignment::create([
            'student_id' => $student->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'Review notes',
            'status' => 'unread',
        ]);

        $this->actingAs($mentor)
            ->get(route('admin.students.assignments.show', $student))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Assignments/Show')
                ->where('student.display_name', 'Ego')
                ->where('assignments.0.title', 'Review notes')
            );
    }

    public function test_assignment_payload_exposes_attachment_url_and_attachment_route_is_access_controlled(): void
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
            'username' => 'other_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Ego',
            'status' => 'active',
            'notes' => null,
        ]);

        Student::create([
            'user_id' => $otherStudentUser->id,
            'display_name' => 'Other Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $storedPath = $this->fakeImageUpload('geometry.png')->store("assignments/{$student->id}", 'local');

        $assignment = StudentAssignment::create([
            'student_id' => $student->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'Geometry',
            'body' => 'Review the image.',
            'attachment_disk' => 'local',
            'attachment_path' => $storedPath,
            'attachment_name' => 'geometry.png',
            'attachment_mime' => 'image/png',
            'attachment_size' => Storage::disk('local')->size($storedPath),
            'status' => 'unread',
        ]);

        $attachmentUrl = route('student-assignments.attachment.show', $assignment);

        $this->actingAs($studentUser)
            ->get(route('student.assignments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Assignments/Index')
                ->where('assignments.0.attachment.url', $attachmentUrl)
                ->where('assignments.0.attachment.name', 'geometry.png')
            );

        $this->actingAs($mentor)
            ->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Assignments/Index')
                ->where('assignments.0.attachment.url', $attachmentUrl)
            );

        $this->actingAs($mentor)
            ->get($attachmentUrl)
            ->assertOk();

        $this->actingAs($studentUser)
            ->get($attachmentUrl)
            ->assertOk();

        $this->actingAs($otherStudentUser)
            ->get($attachmentUrl)
            ->assertNotFound();
    }

    public function test_admin_assignments_pages_expose_complete_and_incomplete_actions_for_submitted_items(): void
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

        $inProgressAssignment = StudentAssignment::create([
            'student_id' => $student->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'In progress item',
            'status' => 'in_progress',
        ]);

        $handedInAssignment = StudentAssignment::create([
            'student_id' => $student->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'Handed in item',
            'status' => 'handed_in',
        ]);

        $completedAssignment = StudentAssignment::create([
            'student_id' => $student->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'Completed item',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($mentor)
            ->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Assignments/Index')
                ->where('assignments.0.status', 'in_progress')
                ->where('assignments.0.id', $inProgressAssignment->id)
                ->where('assignments.0.complete_url', null)
                ->where('assignments.0.incomplete_url', null)
                ->where('assignments.1.status', 'handed_in')
                ->where('assignments.1.id', $handedInAssignment->id)
                ->where('assignments.1.complete_url', route('admin.assignments.complete', $handedInAssignment))
                ->where('assignments.1.incomplete_url', route('admin.assignments.incomplete', $handedInAssignment))
                ->where('assignments.2.status', 'completed')
                ->where('assignments.2.id', $completedAssignment->id)
                ->where('assignments.2.complete_url', null)
                ->where('assignments.2.incomplete_url', route('admin.assignments.incomplete', $completedAssignment))
            );
    }

    public function test_admin_assignments_index_filters_by_selected_student(): void
    {
        $mentor = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $firstStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'filter_a',
        ]);

        $secondStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'filter_b',
        ]);

        $firstStudent = Student::create([
            'user_id' => $firstStudentUser->id,
            'display_name' => 'Filter A',
            'status' => 'active',
            'notes' => null,
        ]);

        $secondStudent = Student::create([
            'user_id' => $secondStudentUser->id,
            'display_name' => 'Filter B',
            'status' => 'active',
            'notes' => null,
        ]);

        $keptAssignment = StudentAssignment::create([
            'student_id' => $firstStudent->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'Keep me',
            'status' => 'unread',
        ]);

        StudentAssignment::create([
            'student_id' => $secondStudent->id,
            'created_by_user_id' => $mentor->id,
            'title' => 'Hide me',
            'status' => 'unread',
        ]);

        $this->actingAs($mentor)
            ->get(route('admin.assignments.index', ['student_id' => $firstStudent->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Assignments/Index')
                ->where('selectedStudentId', $firstStudent->id)
                ->has('assignments', 1)
                ->where('assignments.0.id', $keptAssignment->id)
                ->where('assignments.0.student.id', $firstStudent->id)
            );
    }

    private function fakeImageUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0dkAAAAASUVORK5CYII='),
        );
    }
}
