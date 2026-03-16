<?php

namespace App\Services;

use Carbon\CarbonInterface;

class ScheduleRunFinishWindowService
{
    public function canManuallyFinish(CarbonInterface $moment): bool
    {
        return (int) $moment->format('G') === 19;
    }
}
