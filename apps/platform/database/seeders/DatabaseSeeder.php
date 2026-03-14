<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentConsequenceProfile;
use App\Models\StudentSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate([
            'username' => 'admin',
        ], [
            'name' => 'Local mentor',
            'email' => 'admin@school-system.local',
            'role' => UserRole::Admin,
            'is_active' => true,
            'password' => Hash::make('admin12345'),
        ]);

        $studentUser = User::updateOrCreate([
            'username' => 'student-demo',
        ], [
            'name' => 'Демо-ученик',
            'email' => null,
            'role' => UserRole::Student,
            'is_active' => true,
            'password' => Hash::make('student12345'),
        ]);

        $student = Student::updateOrCreate([
            'user_id' => $studentUser->id,
        ], [
            'display_name' => 'Демо-ученик',
            'status' => 'active',
            'notes' => 'Локальный демонстрационный ученик для проверки ученического интерфейса на раннем этапе разработки.',
        ]);

        StudentSetting::updateOrCreate([
            'student_id' => $student->id,
        ], [
            'can_manage_own_schedule' => true,
            'can_use_ad_hoc_timer' => true,
            'preferred_timezone' => 'UTC',
        ]);

        StudentConsequenceProfile::updateOrCreate([
            'student_id' => $student->id,
        ], [
            'default_push_up_count' => 0,
            'rest_duration_seconds' => 0,
            'legacy_owner_user_id' => null,
            'notes' => null,
        ]);

        $this->call(LegacyTaskTemplateSeeder::class);
        $this->call(LegacyRuleDefinitionSeeder::class);
    }
}
