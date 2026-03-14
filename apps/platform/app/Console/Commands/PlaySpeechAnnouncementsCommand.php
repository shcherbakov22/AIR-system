<?php

namespace App\Console\Commands;

use App\Services\SpeechAnnouncementPlaybackService;
use Illuminate\Console\Command;
use Throwable;

class PlaySpeechAnnouncementsCommand extends Command
{
    protected $signature = 'speech:play-announcements
        {--once : Process at most one announcement and exit}
        {--device=plughw:0,0 : ALSA playback device, for example plughw:0,0}
        {--tts-binary=espeak-ng : Text-to-speech binary}
        {--player-binary=aplay : Audio playback binary}
        {--idle-sleep-ms=2000 : Sleep between empty polls in daemon mode}
        {--stale-after=120 : Reclaim announcement leases older than this many seconds}';

    protected $description = 'Play queued speech announcements through the server audio device.';

    public function handle(SpeechAnnouncementPlaybackService $playbackService): int
    {
        $once = (bool) $this->option('once');
        $device = (string) $this->option('device');
        $ttsBinary = (string) $this->option('tts-binary');
        $playerBinary = (string) $this->option('player-binary');
        $idleSleepMs = max(100, (int) $this->option('idle-sleep-ms'));
        $staleAfterSeconds = max(30, (int) $this->option('stale-after'));

        do {
            try {
                $played = $playbackService->playNextAnnouncement(
                    device: $device,
                    ttsBinary: $ttsBinary,
                    playerBinary: $playerBinary,
                    staleAfterSeconds: $staleAfterSeconds,
                );
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                if ($once) {
                    return self::FAILURE;
                }

                usleep($idleSleepMs * 1000);
                continue;
            }

            if ($once) {
                return self::SUCCESS;
            }

            if (! $played) {
                usleep($idleSleepMs * 1000);
            }
        } while (true);
    }
}
