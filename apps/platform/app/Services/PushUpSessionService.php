<?php

namespace App\Services;

use App\Models\PushUpSession;
use App\Models\PushUpStation;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PushUpSessionService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function defaultConfiguration(int $requiredPushUps): array
    {
        $requiredPushUps = max(1, $requiredPushUps);

        return [
            'sets' => 1,
            'reps' => $requiredPushUps,
            'rest_seconds' => 30,
            'penalty_reps' => 5,
            'drop_threshold' => 20,
            'up_gap' => 6,
            'down_tolerance' => 3,
        ];
    }

    public function toPayload(PushUpSession $session): array
    {
        $session->loadMissing(['student.user', 'violation', 'station']);

        return [
            'id' => $session->id,
            'status' => $session->status,
            'required_push_ups' => $session->required_push_ups,
            'current_rep' => $session->current_rep,
            'current_set' => $session->current_set,
            'configuration' => $session->configuration,
            'claimed_at' => $session->claimed_at?->toAtomString(),
            'started_at' => $session->started_at?->toAtomString(),
            'completed_at' => $session->completed_at?->toAtomString(),
            'notes' => $session->notes,
            'student' => [
                'id' => $session->student->id,
                'display_name' => $session->student->display_name,
                'username' => $session->student->user->username,
            ],
            'violation' => [
                'id' => $session->violation->id,
                'rule_title' => $session->violation->rule_title_snapshot,
                'push_up_count' => $session->violation->penalty_units,
            ],
            'station' => $session->station
                ? [
                    'id' => $session->station->id,
                    'name' => $session->station->name,
                ]
                : null,
        ];
    }

    public function createOrReuse(Violation $violation, ?User $requestedBy): PushUpSession
    {
        if ($violation->status !== 'open') {
            throw new InvalidArgumentException('Only open violations can be sent to the push-up counter.');
        }

        return DB::transaction(function () use ($violation, $requestedBy) {
            $lockedViolation = Violation::query()
                ->whereKey($violation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = PushUpSession::query()
                ->where('violation_id', $lockedViolation->id)
                ->whereIn('status', [
                    self::STATUS_PENDING,
                    self::STATUS_CLAIMED,
                    self::STATUS_RUNNING,
                ])
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            return PushUpSession::create([
                'student_id' => $lockedViolation->student_id,
                'violation_id' => $lockedViolation->id,
                'requested_by_user_id' => $requestedBy?->id,
                'status' => self::STATUS_PENDING,
                'required_push_ups' => $lockedViolation->penalty_units,
                'configuration' => $this->defaultConfiguration($lockedViolation->penalty_units),
                'current_rep' => 0,
                'current_set' => 1,
            ]);
        });
    }

    public function heartbeat(string $stationKey, ?string $stationName, ?User $connectedBy): PushUpStation
    {
        return DB::transaction(function () use ($stationKey, $stationName, $connectedBy) {
            /** @var PushUpStation $station */
            $station = PushUpStation::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['station_key' => $stationKey],
                    ['name' => $stationName, 'connected_by_user_id' => $connectedBy?->id],
                );

            $station->fill([
                'name' => $stationName ?: $station->name,
                'connected_by_user_id' => $connectedBy?->id,
                'last_seen_at' => now(),
            ])->save();

            return $station;
        });
    }

    public function currentSessionForStation(PushUpStation $station): ?PushUpSession
    {
        return PushUpSession::query()
            ->with(['student.user', 'violation', 'station'])
            ->where('push_up_station_id', $station->id)
            ->whereIn('status', [self::STATUS_CLAIMED, self::STATUS_RUNNING])
            ->latest('id')
            ->first();
    }

    public function pendingCount(): int
    {
        return PushUpSession::query()
            ->where('status', self::STATUS_PENDING)
            ->count();
    }

    public function claimNext(PushUpStation $station): ?PushUpSession
    {
        return DB::transaction(function () use ($station) {
            $current = PushUpSession::query()
                ->where('push_up_station_id', $station->id)
                ->whereIn('status', [self::STATUS_CLAIMED, self::STATUS_RUNNING])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($current) {
                return $current->load(['student.user', 'violation', 'station']);
            }

            $next = PushUpSession::query()
                ->where('status', self::STATUS_PENDING)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                return null;
            }

            $next->update([
                'status' => self::STATUS_CLAIMED,
                'push_up_station_id' => $station->id,
                'claimed_at' => now(),
            ]);

            $station->forceFill([
                'last_claimed_at' => now(),
            ])->save();

            return $next->load(['student.user', 'violation', 'station']);
        });
    }

    public function start(PushUpSession $session): PushUpSession
    {
        if (! in_array($session->status, [self::STATUS_CLAIMED, self::STATUS_RUNNING], true)) {
            throw new InvalidArgumentException('This push-up session cannot be started.');
        }

        $session->forceFill([
            'status' => self::STATUS_RUNNING,
            'started_at' => $session->started_at ?? now(),
        ])->save();

        return $session->fresh(['student.user', 'violation', 'station']);
    }

    public function progress(PushUpSession $session, int $currentRep, ?int $currentSet = null): PushUpSession
    {
        if (! in_array($session->status, [self::STATUS_CLAIMED, self::STATUS_RUNNING], true)) {
            throw new InvalidArgumentException('This push-up session cannot receive progress.');
        }

        $session->forceFill([
            'status' => self::STATUS_RUNNING,
            'started_at' => $session->started_at ?? now(),
            'current_rep' => max(0, $currentRep),
            'current_set' => max(1, $currentSet ?? $session->current_set),
        ])->save();

        return $session->fresh(['student.user', 'violation', 'station']);
    }

    public function complete(PushUpSession $session, ?User $completedBy, ?string $notes = null): PushUpSession
    {
        return DB::transaction(function () use ($session, $completedBy, $notes) {
            /** @var PushUpSession $lockedSession */
            $lockedSession = PushUpSession::query()
                ->with(['violation'])
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status === self::STATUS_COMPLETED) {
                return $lockedSession->load(['student.user', 'violation', 'station']);
            }

            $lockedSession->forceFill([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
                'current_rep' => max($lockedSession->current_rep, $lockedSession->required_push_ups),
                'notes' => $notes ?: $lockedSession->notes,
            ])->save();

            $violation = Violation::query()
                ->whereKey($lockedSession->violation_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($violation->status === 'open') {
                $violation->update([
                    'status' => 'resolved',
                ]);

                $violation->resolutions()->create([
                    'action' => 'resolved',
                    'notes' => $notes ?: 'Completed on the push-up counter.',
                    'recorded_at' => now(),
                    'created_by_user_id' => $completedBy?->id,
                ]);
            }

            return $lockedSession->load(['student.user', 'violation', 'station']);
        });
    }

    public function fail(PushUpSession $session, ?string $notes = null): PushUpSession
    {
        if (in_array($session->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true)) {
            return $session->fresh(['student.user', 'violation', 'station']);
        }

        $session->forceFill([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'notes' => $notes ?: $session->notes,
        ])->save();

        return $session->fresh(['student.user', 'violation', 'station']);
    }
}
