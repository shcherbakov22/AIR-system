<?php

namespace Tests\Feature\Student;

use App\Enums\UserRole;
use App\Models\RuleDefinition;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeerSilenceViolationTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(string $username, string $displayName, string $status = 'active'): Student
    {
        $user = User::factory()->create([
            'role' => UserRole::Student,
            'username' => $username,
        ]);

        return Student::create([
            'user_id' => $user->id,
            'display_name' => $displayName,
            'status' => $status,
            'notes' => null,
        ]);
    }

    private function createKeepSilenceRule(User $creator): RuleDefinition
    {
        return RuleDefinition::create([
            'title' => 'Keep silence',
            'description' => 'Keep quiet during work time.',
            'scope' => 'global',
            'student_id' => null,
            'default_penalty_units' => 50,
            'is_active' => true,
            'created_by_user_id' => $creator->id,
        ]);
    }

    public function test_student_home_exposes_other_active_students_for_keep_silence(): void
    {
        $reporter = $this->createStudent('reporter_student', 'Reporter Student');
        $target = $this->createStudent('target_student', 'Target Student');
        $this->createStudent('inactive_student', 'Inactive Student', 'inactive');

        $this->actingAs($reporter->user)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->has('peerSilenceTargets', 1)
                ->where('peerSilenceTargets.0.id', $target->id)
                ->where('peerSilenceTargets.0.username', 'target_student')
                ->where('peerSilenceTargets.0.display_name', 'Target Student')
            );
    }

    public function test_dima_cannot_see_keep_silence_targets(): void
    {
        $reporter = $this->createStudent('dima', 'Dima');
        $this->createStudent('target_for_dima', 'Target For Dima');

        $this->actingAs($reporter->user)
            ->get(route('student.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Home')
                ->where('studentCapabilities.can_report_peer_silence', false)
                ->has('peerSilenceTargets', 0)
            );
    }

    public function test_student_can_give_another_student_keep_silence_violation(): void
    {
        $reporter = $this->createStudent('silence_reporter', 'Silence Reporter');
        $target = $this->createStudent('silence_target', 'Silence Target');
        $rule = $this->createKeepSilenceRule($reporter->user);

        $this->actingAs($reporter->user)
            ->post(route('student.peer-silence-violations.store', $target))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('success', 'Keep silence violation created for Silence Target.');

        $this->assertDatabaseHas('violations', [
            'student_id' => $target->id,
            'rule_definition_id' => $rule->id,
            'status' => 'open',
            'rule_title_snapshot' => 'Keep silence',
            'penalty_units' => 10,
            'reported_by_user_id' => $reporter->user_id,
        ]);
    }

    public function test_student_cannot_give_self_keep_silence_violation(): void
    {
        $reporter = $this->createStudent('self_silence_reporter', 'Self Silence Reporter');
        $this->createKeepSilenceRule($reporter->user);

        $this->actingAs($reporter->user)
            ->post(route('student.peer-silence-violations.store', $reporter))
            ->assertNotFound();

        $this->assertDatabaseCount('violations', 0);
    }

    public function test_dima_cannot_give_keep_silence_violation_by_posting_directly(): void
    {
        $reporter = $this->createStudent('dima', 'Dima');
        $target = $this->createStudent('dima_silence_target', 'Dima Silence Target');
        $this->createKeepSilenceRule($reporter->user);

        $this->actingAs($reporter->user)
            ->post(route('student.peer-silence-violations.store', $target))
            ->assertForbidden();

        $this->assertDatabaseCount('violations', 0);
    }

    public function test_student_keep_silence_violation_is_not_duplicated_when_open(): void
    {
        $reporter = $this->createStudent('duplicate_silence_reporter', 'Duplicate Silence Reporter');
        $target = $this->createStudent('duplicate_silence_target', 'Duplicate Silence Target');
        $rule = $this->createKeepSilenceRule($reporter->user);

        $this->actingAs($reporter->user)
            ->post(route('student.peer-silence-violations.store', $target))
            ->assertRedirect(route('student.home', absolute: false));

        $this->actingAs($reporter->user)
            ->post(route('student.peer-silence-violations.store', $target))
            ->assertRedirect(route('student.home', absolute: false))
            ->assertSessionHas('error', 'Duplicate Silence Target already has an open Keep silence violation.');

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseHas('violations', [
            'student_id' => $target->id,
            'rule_definition_id' => $rule->id,
            'rule_title_snapshot' => 'Keep silence',
        ]);
    }
}
