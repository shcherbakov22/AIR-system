<?php

namespace Database\Seeders;

use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class LegacyTaskTemplateSeeder extends Seeder
{
    /**
     * @return array<int, array{legacy_task_id: int, title: string, default_duration_minutes: int}>
     */
    protected function catalog(): array
    {
        return [
            ['legacy_task_id' => 49, 'title' => 'Chess', 'default_duration_minutes' => 30],
            ['legacy_task_id' => 40, 'title' => 'Chinese', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 6, 'title' => 'Clean up', 'default_duration_minutes' => 20],
            ['legacy_task_id' => 10, 'title' => 'Coding', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 2797, 'title' => 'Crypto', 'default_duration_minutes' => 30],
            ['legacy_task_id' => 5, 'title' => 'Daily duty', 'default_duration_minutes' => 20],
            ['legacy_task_id' => 28, 'title' => 'Dance', 'default_duration_minutes' => 10],
            ['legacy_task_id' => 2793, 'title' => 'Diy', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 123, 'title' => 'Do nothing', 'default_duration_minutes' => 10000],
            ['legacy_task_id' => 43, 'title' => 'Draw', 'default_duration_minutes' => 60],
            ['legacy_task_id' => 47, 'title' => 'Drinking', 'default_duration_minutes' => 5],
            ['legacy_task_id' => 32, 'title' => 'Eating', 'default_duration_minutes' => 20],
            ['legacy_task_id' => 31, 'title' => 'Evening ex', 'default_duration_minutes' => 20],
            ['legacy_task_id' => 39, 'title' => 'Ex order', 'default_duration_minutes' => 60],
            ['legacy_task_id' => 48, 'title' => 'Football', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 25, 'title' => 'Helping', 'default_duration_minutes' => 66],
            ['legacy_task_id' => 2794, 'title' => 'hist', 'default_duration_minutes' => 355],
            ['legacy_task_id' => 3, 'title' => 'Kitchen', 'default_duration_minutes' => 60],
            ['legacy_task_id' => 1, 'title' => 'Math', 'default_duration_minutes' => 50],
            ['legacy_task_id' => 2795, 'title' => 'MD', 'default_duration_minutes' => 50],
            ['legacy_task_id' => 42, 'title' => 'Mentor s time', 'default_duration_minutes' => 120],
            ['legacy_task_id' => 29, 'title' => 'Physics', 'default_duration_minutes' => 60],
            ['legacy_task_id' => 2, 'title' => 'Piano', 'default_duration_minutes' => 50],
            ['legacy_task_id' => 7, 'title' => 'Poetry', 'default_duration_minutes' => 1],
            ['legacy_task_id' => 4, 'title' => 'Push-ups', 'default_duration_minutes' => 10],
            ['legacy_task_id' => 9, 'title' => 'Reading', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 2784, 'title' => 'Reading+', 'default_duration_minutes' => 60],
            ['legacy_task_id' => 37, 'title' => 'Rest 3 min', 'default_duration_minutes' => 3],
            ['legacy_task_id' => 51, 'title' => 'Russian', 'default_duration_minutes' => 50],
            ['legacy_task_id' => 2791, 'title' => 'Singing', 'default_duration_minutes' => 30],
            ['legacy_task_id' => 17, 'title' => 'Spanish', 'default_duration_minutes' => 30],
            ['legacy_task_id' => 24, 'title' => 'Stretching', 'default_duration_minutes' => 60],
            ['legacy_task_id' => 21, 'title' => 'Take a shower', 'default_duration_minutes' => 20],
            ['legacy_task_id' => 50, 'title' => 'Tennis', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 8, 'title' => 'Walking', 'default_duration_minutes' => 200],
            ['legacy_task_id' => 27, 'title' => 'Watch movie', 'default_duration_minutes' => 60],
            ['legacy_task_id' => 2788, 'title' => 'Writing', 'default_duration_minutes' => 60],
            ['legacy_task_id' => 26, 'title' => 'Arms', 'default_duration_minutes' => 140],
            ['legacy_task_id' => 36, 'title' => 'Ball ex', 'default_duration_minutes' => 30],
            ['legacy_task_id' => 12, 'title' => 'BlTyping', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 35, 'title' => 'Board games', 'default_duration_minutes' => 120],
            ['legacy_task_id' => 20, 'title' => 'BrTeeth', 'default_duration_minutes' => 10],
            ['legacy_task_id' => 34, 'title' => 'Cooking', 'default_duration_minutes' => 70],
            ['legacy_task_id' => 45, 'title' => 'Cycling', 'default_duration_minutes' => 200],
            ['legacy_task_id' => 18, 'title' => 'Docs', 'default_duration_minutes' => 70],
            ['legacy_task_id' => 23, 'title' => 'English', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 2790, 'title' => 'EyesEx', 'default_duration_minutes' => 70],
            ['legacy_task_id' => 2786, 'title' => 'History', 'default_duration_minutes' => 60],
            ['legacy_task_id' => 38, 'title' => 'Morning ex', 'default_duration_minutes' => 20],
            ['legacy_task_id' => 2789, 'title' => 'Planka', 'default_duration_minutes' => 10],
            ['legacy_task_id' => 46, 'title' => 'PlayGuitar', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 30, 'title' => 'Pool', 'default_duration_minutes' => 40],
            ['legacy_task_id' => 2785, 'title' => 'PowerLift', 'default_duration_minutes' => 60],
            ['legacy_task_id' => 19, 'title' => 'Pull-ups', 'default_duration_minutes' => 7],
            ['legacy_task_id' => 44, 'title' => 'Rest 5 min', 'default_duration_minutes' => 5],
            ['legacy_task_id' => 15, 'title' => 'Running', 'default_duration_minutes' => 200],
            ['legacy_task_id' => 13, 'title' => 'Russian', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 33, 'title' => 'Science', 'default_duration_minutes' => 55],
            ['legacy_task_id' => 14, 'title' => 'Sleeping', 'default_duration_minutes' => 900],
            ['legacy_task_id' => 11, 'title' => 'Squats', 'default_duration_minutes' => 8],
            ['legacy_task_id' => 22, 'title' => 'Sw pool', 'default_duration_minutes' => 70],
            ['legacy_task_id' => 16, 'title' => 'Swimming', 'default_duration_minutes' => 300],
            ['legacy_task_id' => 41, 'title' => 'Tests', 'default_duration_minutes' => 60],
        ];
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $creatorId = User::query()
            ->orderBy('id')
            ->value('id');

        if (! $creatorId) {
            return;
        }

        foreach ($this->catalog() as $task) {
            TaskTemplate::query()->firstOrCreate(
                ['legacy_task_id' => $task['legacy_task_id']],
                [
                    'title' => $task['title'],
                    'summary' => null,
                    'instructions' => null,
                    'default_duration_minutes' => $task['default_duration_minutes'],
                    'created_by_user_id' => $creatorId,
                ],
            );
        }
    }
}
