<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\ImportReconciliationIssue;
use App\Models\ImportRun;
use App\Models\LegacyRecordLink;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImportIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_an_empty_import_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_imports',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.imports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Imports/Index')
                ->where('summary.runs_total', 0)
                ->where('summary.runs_active', 0)
                ->where('summary.issues_open', 0)
                ->has('importRuns', 0)
                ->has('openIssues', 0)
            );
    }

    public function test_admin_can_view_recorded_import_runs_and_issues(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'username' => 'admin_imports',
            'name' => 'Import Admin',
        ]);

        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'import_student',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'display_name' => 'Import Student',
            'status' => 'active',
            'notes' => null,
        ]);

        $importRun = ImportRun::create([
            'source_system' => 'legacy-mariadb',
            'source_label' => 'Initial student import',
            'status' => 'completed',
            'started_by_user_id' => $admin->id,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'notes' => 'Imported a first student batch.',
            'summary' => ['students' => 1],
        ]);

        LegacyRecordLink::create([
            'import_run_id' => $importRun->id,
            'legacy_system' => 'legacy-mariadb',
            'legacy_table' => 'dle_users',
            'legacy_key' => '42',
            'target_type' => Student::class,
            'target_id' => $student->id,
            'status' => 'linked',
            'payload' => ['username' => 'import_student'],
            'notes' => 'Mapped to the new student row.',
        ]);

        ImportReconciliationIssue::create([
            'import_run_id' => $importRun->id,
            'severity' => 'warning',
            'status' => 'open',
            'legacy_system' => 'legacy-mariadb',
            'legacy_table' => 'parents',
            'legacy_key' => '42',
            'target_type' => Student::class,
            'target_id' => $student->id,
            'summary' => 'Legacy parent row needs manual review.',
            'details' => 'Map the legacy row into student settings instead of a parent account.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.imports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Imports/Index')
                ->where('summary.runs_total', 1)
                ->where('summary.runs_active', 0)
                ->where('summary.issues_open', 1)
                ->where('importRuns.0.source_label', 'Initial student import')
                ->where('importRuns.0.legacy_record_links_count', 1)
                ->where('importRuns.0.reconciliation_issues_count', 1)
                ->where('openIssues.0.summary', 'Legacy parent row needs manual review.')
            );
    }

    public function test_students_are_redirected_away_from_import_routes(): void
    {
        $studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'username' => 'student_imports_blocked',
        ]);

        $this->actingAs($studentUser)
            ->get(route('admin.imports.index'))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
