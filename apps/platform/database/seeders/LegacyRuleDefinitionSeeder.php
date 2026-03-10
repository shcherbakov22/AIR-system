<?php

namespace Database\Seeders;

use App\Models\RuleDefinition;
use App\Models\User;
use Illuminate\Database\Seeder;

class LegacyRuleDefinitionSeeder extends Seeder
{
    /**
     * @return array<int, array{title: string, default_penalty_units: int, description: string}>
     */
    protected function catalog(): array
    {
        return [
            [
                'title' => 'Act as planned',
                'default_penalty_units' => 50,
                'description' => 'Legacy imported violation preset: act according to the agreed plan.',
            ],
            [
                'title' => 'Do what is recorded',
                'default_penalty_units' => 10,
                'description' => 'Legacy imported violation preset: follow what is explicitly recorded.',
            ],
            [
                'title' => 'Don\'t experiment',
                'default_penalty_units' => 50,
                'description' => 'Legacy imported violation preset: do not improvise outside the expected process.',
            ],
            [
                'title' => 'Follow the schedule',
                'default_penalty_units' => 50,
                'description' => 'Legacy imported violation preset: stay aligned with the current schedule.',
            ],
            [
                'title' => 'Keep silence',
                'default_penalty_units' => 50,
                'description' => 'Legacy imported violation preset: keep quiet during work time.',
            ],
            [
                'title' => 'Looked away 10 times',
                'default_penalty_units' => 0,
                'description' => 'Legacy imported violation preset: repeated distraction away from the current work.',
            ],
            [
                'title' => 'Looked away 3 times',
                'default_penalty_units' => 0,
                'description' => 'Legacy imported violation preset: early distraction warning.',
            ],
            [
                'title' => 'No Russian',
                'default_penalty_units' => 50,
                'description' => 'Legacy imported violation preset: speaking Russian when that was disallowed.',
            ],
            [
                'title' => 'Observe the time',
                'default_penalty_units' => 50,
                'description' => 'Legacy imported violation preset: respect timing expectations for the block.',
            ],
        ];
    }

    public function run(): void
    {
        $creatorId = User::query()
            ->orderBy('id')
            ->value('id');

        if (! $creatorId) {
            return;
        }

        foreach ($this->catalog() as $rule) {
            RuleDefinition::query()->updateOrCreate(
                [
                    'title' => $rule['title'],
                    'scope' => 'global',
                    'student_id' => null,
                ],
                [
                    'description' => $rule['description'],
                    'default_penalty_units' => $rule['default_penalty_units'],
                    'is_active' => true,
                    'created_by_user_id' => $creatorId,
                ],
            );
        }
    }
}
