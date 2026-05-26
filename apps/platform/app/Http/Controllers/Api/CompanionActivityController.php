<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCompanionActivityRequest;
use App\Services\ActivityLogService;
use App\Services\StudentAppPolicyService;
use Illuminate\Http\JsonResponse;

class CompanionActivityController extends Controller
{
    public function store(
        StoreCompanionActivityRequest $request,
        StudentAppPolicyService $studentAppPolicyService,
        ActivityLogService $activityLogService,
    ): JsonResponse
    {
        $device = $request->device();
        $eventType = $request->string('event_type')->toString();

        $event = $device->activityEvents()->create([
            'event_type' => $eventType,
            'app_name' => $request->input('app_name'),
            'window_title' => $request->input('window_title'),
            'browser_domain' => $request->input('browser_domain'),
            'payload' => $request->input('payload', []),
            'observed_at' => now(),
        ]);

        if ($eventType === 'open_apps') {
            $studentAppPolicyService->syncOpenApps($device, $request->input('payload.apps', []), $event);
        }

        if ($eventType === 'installed_apps') {
            $studentAppPolicyService->syncInstalledApps($device, $request->input('payload.apps', []));
        }

        if ($eventType === 'app_enforcement') {
            $this->logAppEnforcement($activityLogService, $device, $event, $request->input('payload', []));
        }

        if ($eventType === 'app_close_attempt') {
            $this->logAppCloseAttempt($activityLogService, $device, $event, $request->input('payload', []), $request->input('app_name'));
        }

        if ($eventType === 'update_check') {
            $this->logUpdateCheck($activityLogService, $device, $event, $request->input('payload', []));
        }

        if ($eventType === 'update_download') {
            $this->logClientDiagnostic(
                $activityLogService,
                $device,
                $event,
                'updates',
                'companion_update_download',
                'Companion update download: '.$this->payloadText($request->input('payload', []), ['status', 'result'], 'reported'),
                $request->input('payload', []),
            );
        }

        if ($eventType === 'extension_status') {
            $this->logExtensionStatus($activityLogService, $device, $event, $request->input('payload', []));
        }

        if ($eventType === 'policy_sync') {
            $this->logClientDiagnostic(
                $activityLogService,
                $device,
                $event,
                'devices',
                'companion_policy_sync',
                'Companion policy sync: '.$this->payloadText($request->input('payload', []), ['status', 'reason'], 'reported'),
                $request->input('payload', []),
            );
        }

        if ($eventType === 'client_log') {
            $payload = $request->input('payload', []);
            $this->logClientDiagnostic(
                $activityLogService,
                $device,
                $event,
                $this->payloadText($payload, ['category'], 'devices'),
                $this->payloadText($payload, ['action'], 'client_log'),
                $this->payloadText($payload, ['message', 'description'], 'Client diagnostic reported.'),
                $payload,
            );
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'accepted' => true,
            'event' => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'observed_at' => $event->observed_at?->toAtomString(),
            ],
        ]);
    }

    private function logAppEnforcement(
        ActivityLogService $activityLogService,
        $device,
        $event,
        array $payload,
    ): void {
        $student = $device->student;

        if (! $student) {
            return;
        }

        $closed = collect($payload['closed'] ?? $payload['closed_apps'] ?? [])
            ->filter(fn ($app) => is_array($app))
            ->values();
        $attempted = collect($payload['attempted'] ?? $payload['attempted_apps'] ?? [])
            ->filter(fn ($app) => is_array($app))
            ->values();
        $failures = collect($payload['failures'] ?? [])
            ->filter(fn ($failure) => is_array($failure))
            ->values();
        $reason = $this->payloadText($payload, ['reason', 'close_reason', 'policy_reason'], null);
        $reasonSuffix = $reason ? ': '.$reason : '';

        if ($closed->isNotEmpty() || $attempted->isNotEmpty()) {
            $count = $closed->count() ?: $attempted->count();
            $activityLogService->log(
                'apps',
                'app_enforcement_closed',
                'App enforcement closed '.$count.' process(es)'.$reasonSuffix.'.',
                $student->id,
                null,
                $event,
                [
                    'student_device_id' => $device->id,
                    'reason' => $reason ?: 'policy_enforcement',
                    'closed' => $closed->all(),
                    'attempted' => $attempted->all(),
                    'policy_hash' => $payload['policy_hash'] ?? null,
                    'open_violations' => $payload['open_violations'] ?? null,
                ],
            );
        }

        if ($failures->isNotEmpty()) {
            $activityLogService->log(
                'apps',
                'app_enforcement_failed',
                'App enforcement failed for '.$failures->count().' process(es)'.$reasonSuffix.'.',
                $student->id,
                null,
                $event,
                [
                    'student_device_id' => $device->id,
                    'reason' => $reason ?: 'policy_enforcement',
                    'failures' => $failures->all(),
                    'policy_hash' => $payload['policy_hash'] ?? null,
                ],
            );
        }
    }

    private function logAppCloseAttempt(
        ActivityLogService $activityLogService,
        $device,
        $event,
        array $payload,
        ?string $appName,
    ): void {
        $status = $this->payloadText($payload, ['status', 'result'], isset($payload['success']) && $payload['success'] ? 'closed' : 'attempted');
        $reason = $this->payloadText($payload, ['reason', 'close_reason', 'policy_reason'], 'policy_enforcement');
        $name = $appName ?: $this->payloadText($payload, ['app_name', 'process_name', 'executable'], 'Unknown app');
        $action = in_array($status, ['closed', 'success', 'killed'], true) ? 'app_closed' : 'app_close_attempted';

        $this->logClientDiagnostic(
            $activityLogService,
            $device,
            $event,
            'apps',
            $action,
            ($action === 'app_closed' ? 'Closed app: ' : 'App close attempted: ').$name.' ('.$reason.').',
            [
                'student_device_id' => $device->id,
                'app_name' => $name,
                'status' => $status,
                'reason' => $reason,
                ...$payload,
            ],
        );
    }

    private function logUpdateCheck(
        ActivityLogService $activityLogService,
        $device,
        $event,
        array $payload,
    ): void {
        $current = $this->payloadText($payload, ['current_version', 'installed_version'], null);
        $latest = $this->payloadText($payload, ['latest_version', 'available_version'], null);
        $status = $this->payloadText($payload, ['status', 'result'], $latest && $current && $latest !== $current ? 'available' : 'checked');
        $summary = trim(($current ? $current : 'unknown').' -> '.($latest ? $latest : 'unknown'));

        $this->logClientDiagnostic(
            $activityLogService,
            $device,
            $event,
            'updates',
            'companion_update_check',
            'Companion update check: '.$status.' ('.$summary.').',
            $payload,
        );
    }

    private function logExtensionStatus(
        ActivityLogService $activityLogService,
        $device,
        $event,
        array $payload,
    ): void {
        $status = $this->payloadText($payload, ['status', 'reason'], 'reported');
        $configured = array_key_exists('configured', $payload) ? (bool) $payload['configured'] : null;
        $version = $this->payloadText($payload, ['version', 'extension_version'], null);
        $detail = $configured === null ? $status : $status.($configured ? ' configured' : ' unconfigured');

        if ($version) {
            $detail .= ' v'.$version;
        }

        $this->logClientDiagnostic(
            $activityLogService,
            $device,
            $event,
            'extensions',
            'extension_status',
            'Browser extension status: '.$detail.'.',
            $payload,
        );
    }

    private function logClientDiagnostic(
        ActivityLogService $activityLogService,
        $device,
        $event,
        string $category,
        string $action,
        string $description,
        array $payload,
    ): void {
        $student = $device->student;

        if (! $student) {
            return;
        }

        $activityLogService->log(
            $category,
            $action,
            $description,
            $student->id,
            null,
            $event,
            [
                'student_device_id' => $device->id,
                ...$payload,
            ],
        );
    }

    private function payloadText(array $payload, array $keys, ?string $fallback): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_numeric($value) || is_bool($value)) {
                return (string) $value;
            }
        }

        return $fallback;
    }
}
