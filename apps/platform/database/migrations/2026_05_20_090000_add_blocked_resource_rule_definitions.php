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

        foreach ($this->rules() as $rule) {
            DB::table('rule_definitions')->updateOrInsert(
                [
                    'title' => $rule['title'],
                    'scope' => 'global',
                    'student_id' => null,
                ],
                [
                    'description' => $rule['description'],
                    'default_penalty_units' => 50,
                    'is_active' => true,
                    'created_by_user_id' => $creatorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('rule_definitions')
            ->whereIn('title', array_column($this->rules(), 'title'))
            ->where('scope', 'global')
            ->whereNull('student_id')
            ->delete();
    }

    /**
     * @return array<int, array{title: string, description: string}>
     */
    private function rules(): array
    {
        return [
            [
                'title' => 'Blocked program opened',
                'description' => 'Automatic violation preset: student opened a program blocked by the current app policy.',
            ],
            [
                'title' => 'Blocked website opened',
                'description' => 'Automatic violation preset: student opened a website blocked by the current browser policy.',
            ],
        ];
    }
};
