<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\SpeechAnnouncement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class SpeechAnnouncementPlaybackService
{
    public const ENABLED_SETTING_KEY = 'server_speech_enabled';

    public function isEnabled(): bool
    {
        return AppSetting::getBoolean(self::ENABLED_SETTING_KEY, true);
    }

    public function setEnabled(bool $enabled): void
    {
        AppSetting::putBoolean(self::ENABLED_SETTING_KEY, $enabled);
    }

    public function releaseExpiredClaims(int $staleAfterSeconds = 120): int
    {
        return SpeechAnnouncement::query()
            ->whereNull('spoken_at')
            ->whereNotNull('processing_started_at')
            ->where('processing_started_at', '<=', now()->subSeconds($staleAfterSeconds))
            ->update([
                'processing_started_at' => null,
                'processing_host' => null,
                'updated_at' => now(),
            ]);
    }

    public function claimNextAnnouncement(int $staleAfterSeconds = 120): ?SpeechAnnouncement
    {
        $this->releaseExpiredClaims($staleAfterSeconds);

        return DB::transaction(function () {
            $announcement = SpeechAnnouncement::query()
                ->whereNull('spoken_at')
                ->whereNull('processing_started_at')
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if (! $announcement) {
                return null;
            }

            $announcement->update([
                'processing_started_at' => now(),
                'processing_host' => (string) gethostname(),
            ]);

            return $announcement->fresh();
        });
    }

    public function playAnnouncement(
        SpeechAnnouncement $announcement,
        string $device = 'plughw:0,0',
        string $ttsBinary = 'espeak-ng',
        string $playerBinary = 'aplay',
    ): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'speech-announcement-');

        if ($tempFile === false) {
            throw new RuntimeException('Could not allocate a temporary speech file.');
        }

        $wavFile = $tempFile.'.wav';
        @unlink($tempFile);

        try {
            $ttsProcess = Process::timeout(30)->run([
                $ttsBinary,
                '-v',
                'en-us',
                '-s',
                '165',
                '-w',
                $wavFile,
                $announcement->message,
            ]);

            if ($ttsProcess->failed()) {
                throw new RuntimeException(trim($ttsProcess->errorOutput() ?: $ttsProcess->output()) ?: 'Speech synthesis failed.');
            }

            $playProcess = Process::timeout(60)->run([
                $playerBinary,
                '-D',
                $device,
                '-q',
                $wavFile,
            ]);

            if ($playProcess->failed()) {
                throw new RuntimeException(trim($playProcess->errorOutput() ?: $playProcess->output()) ?: 'Audio playback failed.');
            }
        } finally {
            if (is_file($wavFile)) {
                @unlink($wavFile);
            }
        }
    }

    public function markAnnouncementSpoken(SpeechAnnouncement $announcement): void
    {
        $announcement->update([
            'spoken_at' => now(),
            'processing_started_at' => null,
            'processing_host' => null,
        ]);
    }

    public function releaseClaim(SpeechAnnouncement $announcement): void
    {
        $announcement->update([
            'processing_started_at' => null,
            'processing_host' => null,
        ]);
    }

    public function playNextAnnouncement(
        string $device = 'plughw:0,0',
        string $ttsBinary = 'espeak-ng',
        string $playerBinary = 'aplay',
        int $staleAfterSeconds = 120,
    ): bool {
        if (! $this->isEnabled()) {
            return false;
        }

        $announcement = $this->claimNextAnnouncement($staleAfterSeconds);

        if (! $announcement) {
            return false;
        }

        try {
            $this->playAnnouncement($announcement, $device, $ttsBinary, $playerBinary);
            $this->markAnnouncementSpoken($announcement);

            return true;
        } catch (Throwable $exception) {
            $this->releaseClaim($announcement);

            throw $exception;
        }
    }
}
