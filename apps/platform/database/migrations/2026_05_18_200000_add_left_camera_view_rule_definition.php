<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $creatorId = DB::table('users')->orderBy('id')->value('id');

        if (! $creatorId) {
            return;
        }

        DB::table('rule_definitions')->updateOrInsert(
            [
                'title' => 'Left camera view',
                'scope' => 'global',
                'student_id' => null,
            ],
            [
                'description' => 'Automatic violation preset: student left the camera view during the current task.',
                'default_penalty_units' => 0,
                'is_active' => true,
                'created_by_user_id' => $creatorId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('rule_definitions')
            ->where('title', 'Left camera view')
            ->where('scope', 'global')
            ->whereNull('student_id')
            ->delete();
    }
};
