<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\AutomaticObserveTheTimeViolationService;
use Illuminate\Console\Command;

class EvaluateObserveTheTimeViolationsCommand extends Command
{
    protected $signature = 'violations:evaluate-observe-the-time';

    protected $description = 'Evaluate automatic Observe the time violations for students with active work.';

    public function handle(AutomaticObserveTheTimeViolationService $automaticViolationService): int
    {
        $evaluated = 0;

        Student::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(100, function ($students) use ($automaticViolationService, &$evaluated) {
                foreach ($students as $student) {
                    $automaticViolationService->evaluate($student);
                    $evaluated++;
                }
            });

        $this->info("Evaluated Observe the time for {$evaluated} students.");

        return self::SUCCESS;
    }
}
