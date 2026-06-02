<?php

namespace App\Services;

use App\Models\PushUpSession;
use App\Models\PushUpStudentProfile;

class PushUpStudentProfileService
{
    public function payloadForSession(PushUpSession $session): ?array
    {
        $profile = $session->student?->pushUpProfile;

        if (! $profile || $profile->sample_count < 1 || ($profile->confidence ?? 0) < 20) {
            return null;
        }

        return [
            'sample_count' => $profile->sample_count,
            'top_distance' => round((float) $profile->top_distance, 1),
            'down_distance' => round((float) $profile->down_distance, 1),
            'amplitude' => round((float) $profile->amplitude, 1),
            'return_distance' => round((float) $profile->return_distance, 1),
            'noise_cm' => round((float) $profile->noise_cm, 1),
            'average_rep_duration_ms' => round((float) $profile->average_rep_duration_ms, 1),
            'confidence' => round((float) $profile->confidence, 1),
        ];
    }

    public function learnFromSession(PushUpSession $session, array $metrics): ?PushUpStudentProfile
    {
        $amplitude = (float) ($metrics['average_amplitude'] ?? $metrics['amplitude'] ?? 0);
        $topDistance = (float) ($metrics['average_top_distance'] ?? $metrics['top_distance'] ?? 0);
        $downDistance = (float) ($metrics['average_down_distance'] ?? $metrics['down_distance'] ?? 0);
        $returnDistance = (float) ($metrics['average_return_distance'] ?? $metrics['return_distance'] ?? 0);
        $noise = (float) ($metrics['noise_cm'] ?? 0);
        $duration = (float) ($metrics['average_rep_duration_ms'] ?? 0);
        $reps = (int) ($metrics['rep_count'] ?? $session->current_rep ?? 0);

        if ($reps < 3 || $amplitude < 8 || $topDistance <= 0 || $downDistance <= 0 || $downDistance <= $topDistance) {
            return null;
        }

        $profile = PushUpStudentProfile::firstOrNew(['student_id' => $session->student_id]);
        $sampleCount = (int) $profile->sample_count;
        $alpha = $sampleCount < 3 ? 0.45 : 0.25;

        $blend = fn (?float $old, float $new): float => $old === null || $old <= 0
            ? $new
            : (($old * (1 - $alpha)) + ($new * $alpha));

        $profile->fill([
            'sample_count' => $sampleCount + 1,
            'top_distance' => $blend($profile->top_distance, $topDistance),
            'down_distance' => $blend($profile->down_distance, $downDistance),
            'amplitude' => $blend($profile->amplitude, $amplitude),
            'return_distance' => $blend($profile->return_distance, max(4, $returnDistance)),
            'noise_cm' => $blend($profile->noise_cm, max(0, $noise)),
            'average_rep_duration_ms' => $duration > 0 ? $blend($profile->average_rep_duration_ms, $duration) : $profile->average_rep_duration_ms,
            'confidence' => min(100, max((float) $profile->confidence, 15) + min(15, max(3, $reps / 3))),
            'last_calibrated_at' => now(),
            'metrics' => $metrics,
        ])->save();

        return $profile;
    }
}
