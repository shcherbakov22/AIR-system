<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $earlyFinishTitles = [
        'clean up',
        'meditation',
        'cooking',
        'eating',
        'kitchen',
        'daily duty',
        'ex order',
        'walking',
        'drinking',
        'pull ups',
        'kite',
        'md',
        'mentor s time',
        'mentors time',
        'take a shower',
        'push ups',
        'rest 3 min',
        'rest 5 min',
        'running',
        'sleeping',
        'tennis',
        'guitar',
        'piano',
    ];

    /**
     * @var array<int, string>
     */
    private array $interruptTitles = [
        'rest 3 min',
        'rest 5 min',
        'eating',
        'cooking',
        'kitchen',
        'pull ups',
        'walking',
        'drinking',
        'push ups',
        'ex order',
        'tennis',
    ];

    public function up(): void
    {
        foreach (DB::table('task_templates')->select(['id', 'title'])->get() as $taskTemplate) {
            DB::table('task_templates')
                ->where('id', $taskTemplate->id)
                ->update([
                    'can_end_early' => $this->matches($taskTemplate->title, $this->earlyFinishTitles),
                    'can_interrupt_schedule' => $this->matches($taskTemplate->title, $this->interruptTitles),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('task_templates')->update([
            'can_end_early' => false,
            'can_interrupt_schedule' => false,
        ]);
    }

    /**
     * @param array<int, string> $allowedTitles
     */
    private function matches(string $title, array $allowedTitles): bool
    {
        $normalizedTitle = $this->normalize($title);
        $compactTitle = str_replace(' ', '', $normalizedTitle);

        foreach ($allowedTitles as $allowedTitle) {
            $normalizedAllowedTitle = $this->normalize($allowedTitle);
            $compactAllowedTitle = str_replace(' ', '', $normalizedAllowedTitle);

            if ($normalizedTitle === $normalizedAllowedTitle || $compactTitle === $compactAllowedTitle) {
                return true;
            }

            if ($normalizedAllowedTitle === 'guitar' && str_contains($compactTitle, 'guitar')) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $title): string
    {
        $normalized = strtolower($title);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }
};
