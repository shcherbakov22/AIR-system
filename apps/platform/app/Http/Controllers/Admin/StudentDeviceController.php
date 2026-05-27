<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManageStudentDeviceRequest;
use App\Models\DeviceCommand;
use App\Models\DeviceHeartbeat;
use App\Models\DeviceActivityEvent;
use App\Models\BrowserAccessRequest;
use App\Models\BrowserPolicyRule;
use App\Models\BrowserVisitLog;
use App\Models\Student;
use App\Models\StudentMonitorCapture;
use App\Models\StudentDevice;
use App\Services\DevicePolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class StudentDeviceController extends Controller
{
    public function index(Student $student, DevicePolicyService $devicePolicyService): Response
    {
        $student->load([
            'user',
            'browserPolicyRules',
            'browserAccessRequests' => fn ($query) => $query->latest('created_at')->limit(20),
            'browserVisitLogs' => fn ($query) => $query->latest('visited_at')->latest('id')->limit(40),
            'devices.commands.results',
            'devices.monitorCaptures' => fn ($query) => $query->latest('captured_at')->limit(2),
        ]);

        $devices = $student->devices
            ->whereNull('revoked_at')
            ->reject(fn (StudentDevice $device) => $device->platform === 'chrome_extension')
            ->sortBy('label')
            ->values();

        return Inertia::render('Admin/Students/Devices', [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user?->username,
            ],
            'browser_accountability' => $this->browserAccountabilityPayload($student),
            'devices' => $devices
                ->map(fn (StudentDevice $device) => $this->deviceCardPayload($device, $devicePolicyService))
                ->all(),
        ]);
    }

    public function debug(Student $student, DevicePolicyService $devicePolicyService): Response
    {
        $student->load([
            'user',
            'browserPolicyRules',
            'browserAccessRequests' => fn ($query) => $query->latest('created_at')->limit(50),
            'browserVisitLogs' => fn ($query) => $query->latest('visited_at')->latest('id')->limit(100),
            'devices.heartbeats' => fn ($query) => $query->latest('received_at')->latest('id')->limit(20),
            'devices.activityEvents' => fn ($query) => $query->latest('observed_at')->latest('id')->limit(40),
            'devices.commands.results',
            'devices.monitorCaptures' => fn ($query) => $query->latest('captured_at')->latest('id')->limit(20),
        ]);

        $devices = $student->devices
            ->whereNull('revoked_at')
            ->reject(fn (StudentDevice $device) => $device->platform === 'chrome_extension')
            ->sortBy('label')
            ->values();

        return Inertia::render('Admin/Students/CompanionDebug', [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user?->username,
            ],
            'browser_accountability' => $this->browserAccountabilityPayload($student),
            'devices' => $devices
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
    ): RedirectResponse
    {
        abort_unless($studentDevice->student_id === $student->id, 404);

        $commandType = $request->string('command_type')->toString();
        $payload = $request->input('payload', []);

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
        ];
    }

    protected function browserAccountabilityPayload(Student $student): array
    {
        return [
            'mode' => $student->devices
                ->first(fn (StudentDevice $device) => in_array($device->internet_access_mode, ['whitelist', 'blacklist'], true))
                ?->internet_access_mode ?? 'blacklist',
            'default_unblock_scope' => 'domain_tree',
            'rules' => $student->browserPolicyRules
                ->sortBy('value')
                ->values()
                ->map(fn (BrowserPolicyRule $rule) => [
                    'id' => $rule->id,
                    'effect' => $rule->effect,
                    'match_type' => $rule->match_type,
                    'value' => $rule->value,
                    'expires_at' => $rule->expires_at?->toAtomString(),
                ])->all(),
            'pending_requests' => $student->browserAccessRequests
                ->where('status', 'pending')
                ->values()
                ->map(fn (BrowserAccessRequest $request) => [
                    'id' => $request->id,
                    'requested_url' => $request->requested_url,
                    'host' => $request->host,
                    'registrable_domain' => $request->registrable_domain,
                    'task_template_id' => $request->task_template_id,
                    'reason' => $request->reason,
                    'created_at' => $request->created_at?->toAtomString(),
                ])->all(),
            'recent_visits' => $student->browserVisitLogs
                ->values()
                ->map(fn (BrowserVisitLog $visit) => [
                    'id' => $visit->id,
                    'mode' => $visit->mode,
                    'decision' => $visit->decision,
                    'host' => $visit->host,
                    'registrable_domain' => $visit->registrable_domain,
                    'page_title' => $visit->page_title,
                    'visited_at' => $visit->visited_at?->toAtomString(),
                    'matched_rule_id' => $visit->matched_rule_id,
                ])->all(),
        ];
    }
}
