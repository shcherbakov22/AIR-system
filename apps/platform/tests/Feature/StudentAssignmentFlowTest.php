<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'title' => 'Read chapter 2',
                'body' => 'Summarize the main ideas.',
            ])
            ->assertRedirect(route('admin.assignments.index', ['student_id' => $student->id], absolute: false))
            ->assertSessionHas('success', 'Assignment created.');

        $this->assertDatabaseHas('student_assignments', [
            'student_id' => $student->id,
            'title' => 'Read chapter 2',
            'status' => 'unread',
        ]);
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

    public function test_admin_assignments_pages_expose_complete_action_only_for_handed_in_items(): void
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

        $this->actingAs($mentor)
            ->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Assignments/Index')
                ->where('assignments.0.status', 'in_progress')
                ->where('assignments.0.id', $inProgressAssignment->id)
                ->where('assignments.0.complete_url', null)
                ->where('assignments.1.status', 'handed_in')
                ->where('assignments.1.id', $handedInAssignment->id)
                ->where('assignments.1.complete_url', route('admin.assignments.complete', $handedInAssignment))
            );
    }
}
