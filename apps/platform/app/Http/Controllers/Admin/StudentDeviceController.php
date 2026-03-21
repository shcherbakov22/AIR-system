<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManageStudentDeviceRequest;
use App\Models\DeviceCommand;
use App\Models\DeviceHeartbeat;
use App\Models\DeviceActivityEvent;
use App\Models\Student;
use App\Models\StudentMonitorCapture;
use App\Models\StudentDevice;
use App\Services\DevicePolicyService;
use App\Services\GatewayPolicyService;
use App\Services\RemoteControlCredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class StudentDeviceController extends Controller
{
    public function index(Student $student, DevicePolicyService $devicePolicyService): Response
    {
        $student->load([
            'user',
            'devices.commands.results',
            'devices.remoteControlSessions' => fn ($query) => $query->latest('started_at')->latest('id')->limit(1),
            'devices.monitorCaptures' => fn ($query) => $query->latest('captured_at')->limit(2),
        ]);

        return Inertia::render('Admin/Students/Devices', [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user?->username,
            ],
            'devices' => $student->devices
                ->whereNull('revoked_at')
                ->sortBy('label')
                ->values()
                ->map(fn (StudentDevice $device) => $this->deviceCardPayload($device, $devicePolicyService))
                ->all(),
        ]);
    }

    public function debug(Student $student, DevicePolicyService $devicePolicyService): Response
    {
        $student->load([
            'user',
            'devices.heartbeats' => fn ($query) => $query->latest('received_at')->latest('id')->limit(20),
            'devices.activityEvents' => fn ($query) => $query->latest('observed_at')->latest('id')->limit(40),
            'devices.commands.results',
            'devices.remoteControlSessions' => fn ($query) => $query->latest('started_at')->latest('id')->limit(5),
            'devices.monitorCaptures' => fn ($query) => $query->latest('captured_at')->latest('id')->limit(20),
        ]);

        return Inertia::render('Admin/Students/CompanionDebug', [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user?->username,
            ],
            'devices' => $student->devices
                ->whereNull('revoked_at')
                ->sortBy('label')
                ->values()
                ->map(fn (StudentDevice $device) => [
                    ...$this->deviceCardPayload($device, $devicePolicyService),
                    'policy_snapshot' => $devicePolicyService->buildForDevice($device),
                    'heartbeats' => $device->heartbeats
                        ->sortByDesc(fn (DeviceHeartbeat $heartbeat) => [
                            optional($heartbeat->received_at)?->timestamp ?? 0,
                            $heartbeat->id,
                        ])
                        ->values()
                        ->map(fn (DeviceHeartbeat $heartbeat) => [
                            'id' => $heartbeat->id,
                            'received_at' => $heartbeat->received_at?->toAtomString(),
                            'ip_address' => $heartbeat->ip_address,
                            'payload' => $heartbeat->payload ?? [],
                        ])->all(),
                    'activity_events' => $device->activityEvents
                        ->sortByDesc(fn (DeviceActivityEvent $event) => [
                            optional($event->observed_at)?->timestamp ?? 0,
                            $event->id,
                        ])
                        ->values()
                        ->map(fn (DeviceActivityEvent $event) => [
                            'id' => $event->id,
                            'event_type' => $event->event_type,
                            'app_name' => $event->app_name,
                            'window_title' => $event->window_title,
                            'browser_domain' => $event->browser_domain,
                            'observed_at' => $event->observed_at?->toAtomString(),
                            'payload' => $event->payload ?? [],
                        ])->all(),
                    'captures' => $device->monitorCaptures
                        ->sortByDesc(fn (StudentMonitorCapture $capture) => [
                            optional($capture->captured_at)?->timestamp ?? 0,
                            $capture->id,
                        ])
                        ->values()
                        ->map(fn (StudentMonitorCapture $capture) => [
                            'id' => $capture->id,
                            'capture_kind' => $capture->capture_kind,
                            'captured_at' => $capture->captured_at?->toAtomString(),
                            'uploaded_at' => $capture->uploaded_at?->toAtomString(),
                            'task_title_snapshot' => $capture->task_title_snapshot,
                            'app_name_snapshot' => $capture->app_name_snapshot,
                            'window_title_snapshot' => $capture->window_title_snapshot,
                            'browser_domain_snapshot' => $capture->browser_domain_snapshot,
                            'source_label' => $capture->source_label,
                            'source_version' => $capture->source_version,
                            'mime_type' => $capture->mime_type,
                            'size_bytes' => $capture->size_bytes,
                            'meta' => $capture->meta ?? [],
                            'show_url' => route('admin.student-monitor-captures.show', $capture),
                        ])->all(),
                    'commands' => $device->commands
                        ->sortByDesc(fn (DeviceCommand $command) => [
                            optional($command->requested_at)?->timestamp ?? 0,
                            $command->id,
                        ])
                        ->values()
                        ->map(fn (DeviceCommand $command) => [
                            'id' => $command->id,
                            'command_type' => $command->command_type,
                            'status' => $command->status,
                            'payload' => $command->payload ?? [],
                            'requested_at' => $command->requested_at?->toAtomString(),
                            'leased_at' => $command->leased_at?->toAtomString(),
                            'completed_at' => $command->completed_at?->toAtomString(),
                            'results' => $command->results
                                ->sortByDesc(fn ($result) => [
                                    optional($result->received_at)?->timestamp ?? 0,
                                    $result->id,
                                ])
                                ->values()
                                ->map(fn ($result) => [
                                    'id' => $result->id,
                                    'status' => $result->status,
                                    'received_at' => $result->received_at?->toAtomString(),
                                    'payload' => $result->payload ?? [],
                                ])->all(),
                                ])->all(),
                    'remote_control_sessions' => $device->remoteControlSessions
                        ->sortByDesc(fn ($session) => [
                            optional($session->started_at)?->timestamp ?? 0,
                            $session->id,
                        ])
                        ->values()
                        ->map(fn ($session) => [
                            'id' => $session->id,
                            'status' => $session->status,
                            'started_at' => $session->started_at?->toAtomString(),
                            'ended_at' => $session->ended_at?->toAtomString(),
                            'failure_reason' => $session->failure_reason,
                            'viewer_url' => $session->viewer_path,
                            'show_url' => route('admin.remote-control-sessions.show', $session),
                        ])->all(),
                ])->all(),
        ]);
    }

    public function update(ManageStudentDeviceRequest $request, Student $student, StudentDevice $studentDevice): RedirectResponse
    {
        abort_unless($studentDevice->student_id === $student->id, 404);

        $studentDevice->update([
            'label' => $request->input('label') ?: $studentDevice->label,
        ]);

        return redirect()
            ->route('admin.students.devices.index', $student)
            ->with('success', "Device {$studentDevice->label} updated.");
    }

    public function revoke(ManageStudentDeviceRequest $request, Student $student, StudentDevice $studentDevice): RedirectResponse
    {
        abort_unless($studentDevice->student_id === $student->id, 404);

        $studentDevice->forceFill([
            'revoked_at' => now(),
            'revoked_by_user_id' => $request->user()->id,
        ])->save();

        return redirect()
            ->route('admin.students.devices.index', $student)
            ->with('success', "Device {$studentDevice->label} revoked.");
    }

    public function command(
        ManageStudentDeviceRequest $request,
        Student $student,
        StudentDevice $studentDevice,
        RemoteControlCredentialService $remoteControlCredentialService,
    ): RedirectResponse
    {
        abort_unless($studentDevice->student_id === $student->id, 404);

        $commandType = $request->string('command_type')->toString();
        $payload = $request->input('payload', []);

        if (in_array($commandType, ['enable_remote_access', 'refresh_remote_credentials'], true)
            && empty($payload)) {
            $payload = $remoteControlCredentialService->ensureCredentials($studentDevice);
        }

        $studentDevice->commands()->create([
            'requested_by_user_id' => $request->user()->id,
            'command_type' => $commandType,
            'status' => 'pending',
            'payload' => $payload,
            'requested_at' => now(),
        ]);

        return redirect()
            ->route('admin.students.devices.index', $student)
            ->with('success', "Command queued for {$studentDevice->label}.");
    }

    public function updateInternetAccess(
        ManageStudentDeviceRequest $request,
        Student $student,
        StudentDevice $studentDevice,
        GatewayPolicyService $gatewayPolicyService,
    ): RedirectResponse {
        abort_unless($studentDevice->student_id === $student->id, 404);
        abort_if($studentDevice->revoked_at !== null, 404);

        $mode = $request->string('internet_access_mode')->toString();
        $networkState = (bool) config('services.network_control.enabled', false)
            ? [
                'status' => (bool) config('services.network_control.local_gateway_enabled', false)
                    ? 'pending_local_sync'
                    : 'configured',
                'reason' => $mode === 'block_all' ? 'admin_device_block' : 'admin_device_allow',
                'policy' => [
                    'mode' => $mode,
                    'internet_allowed' => $mode === 'allow_all',
                ],
            ]
            : [
                'status' => 'disabled',
                'reason' => 'network_control_disabled',
                'policy' => [
                    'mode' => 'allow_all',
                    'internet_allowed' => true,
                ],
            ];

        $studentDevice->update([
            'internet_access_mode' => $mode,
            'last_network_state' => $networkState,
        ]);

        $message = $mode === 'block_all'
            ? "Internet blocked for {$studentDevice->label}."
            : "Internet allowed for {$studentDevice->label}.";

        if ((bool) config('services.network_control.enabled', false)
            && (bool) config('services.network_control.local_gateway_enabled', false)) {
            try {
                $gatewayPolicyService->applyRuleset();
                $message .= ' Gateway sync applied immediately.';
            } catch (Throwable $exception) {
                return redirect()
                    ->route('admin.students.devices.debug', $student)
                    ->with('error', 'Gateway sync failed: '.$exception->getMessage());
            }
        }

        return redirect()
            ->route('admin.students.devices.debug', $student)
            ->with('success', $message);
    }

    protected function deviceCardPayload(StudentDevice $device, DevicePolicyService $devicePolicyService): array
    {
        return [
            'id' => $device->id,
            'device_key' => $device->device_key,
            'label' => $device->label,
            'hostname' => $device->hostname,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'last_seen_at' => $device->last_seen_at?->toAtomString(),
            'last_seen_ip' => $device->last_seen_ip,
            'last_ipv4' => $device->last_ipv4,
            'last_mac_address' => $device->last_mac_address,
            'last_gateway_ipv4' => $device->last_gateway_ipv4,
            'network_adapter_name' => $device->network_adapter_name,
            'internet_access_mode' => $device->internet_access_mode,
            'remote_control_ready' => $device->remote_control_ready,
            'remote_control_last_checked_at' => $device->remote_control_last_checked_at?->toAtomString(),
            'remote_control_failure_reason' => $device->remote_control_failure_reason,
            'revoked_at' => $device->revoked_at?->toAtomString(),
            'last_network_state' => $device->last_network_state ?? [],
            'meta' => $device->meta ?? [],
            'policy' => Arr::only($devicePolicyService->buildForDevice($device)['internet_policy'], [
                'mode',
                'internet_allowed',
                'reason',
                'allowed_domains',
            ]),
            'activity' => $devicePolicyService->latestActivitySummary($device),
            'latest_captures' => $device->monitorCaptures->map(fn ($capture) => [
                'id' => $capture->id,
                'capture_kind' => $capture->capture_kind,
                'captured_at' => $capture->captured_at?->toAtomString(),
            ])->all(),
            'commands' => $device->commands
                ->sortByDesc('requested_at')
                ->take(5)
                ->values()
                ->map(fn (DeviceCommand $command) => [
                    'id' => $command->id,
                    'command_type' => $command->command_type,
                    'status' => $command->status,
                    'requested_at' => $command->requested_at?->toAtomString(),
                    'completed_at' => $command->completed_at?->toAtomString(),
                ])->all(),
            'latest_remote_session' => optional($device->remoteControlSessions->sortByDesc('started_at')->first(), function ($session) {
                return [
                    'id' => $session->id,
                    'status' => $session->status,
                    'started_at' => $session->started_at?->toAtomString(),
                    'show_url' => route('admin.remote-control-sessions.show', $session),
                ];
            }),
        ];
    }
}
