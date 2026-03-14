<?php

namespace App\Console\Commands;

use App\Models\StudentMonitorCapture;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeStudentMonitorCapturesCommand extends Command
{
    protected $signature = 'monitor:purge-student-captures {--before=}';

    protected $description = 'Delete student monitor captures before the given day boundary.';

    public function handle(): int
    {
        $beforeOption = $this->option('before');
        $threshold = $beforeOption
            ? Carbon::parse((string) $beforeOption)->startOfDay()
            : now()->startOfDay();

        $captures = StudentMonitorCapture::query()
            ->where(function ($query) use ($threshold) {
                $query
                    ->whereNotNull('captured_at')
                    ->where('captured_at', '<', $threshold);
            })
            ->orWhere(function ($query) use ($threshold) {
                $query
                    ->whereNull('captured_at')
                    ->whereNotNull('uploaded_at')
                    ->where('uploaded_at', '<', $threshold);
            })
            ->get();

        $deleted = 0;

        DB::transaction(function () use ($captures, &$deleted) {
            foreach ($captures as $capture) {
                Storage::disk($capture->disk)->delete($capture->path);
                $capture->delete();
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} student monitor captures older than {$threshold->toDateString()}.");

        return self::SUCCESS;
    }
}
