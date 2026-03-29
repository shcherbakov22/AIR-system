<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManageStudentDeviceRequest;
use App\Models\RemoteControlSession;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\DeviceCommand;
use App\Services\RemoteControlGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RemoteControlSessionController extends Controller
{
    public function store(
        ManageStudentDeviceRequest $request,
        Student $student,
        StudentDevice $studentDevice,
        RemoteControlGatewayService $gatewayService,
    ): RedirectResponse {
        abort_unless($studentDevice->student_id === $student->id, 404);
        abort_if($studentDevice->revoked_at !== null, 404);

        if (! (bool) config('services.remote_control.enabled', true)) {
            return redirect()
                ->route('admin.students.devices.index', $student)
                ->with('error', 'Remote control is disabled.');
        }

        $heartbeatMaxAgeSeconds = (int) config('services.remote_control.ready_heartbeat_max_age_seconds', 120);
        $recentHeartbeat = $studentDevice->last_seen_at !== null
            && $studentDevice->last_seen_at->greaterThanOrEqualTo(now()->subSeconds($heartbeatMaxAgeSeconds));

        if (! $studentDevice->last_ipv4 || ! $recentHeartbeat) {
            $this->queueDeviceCommand($studentDevice, $request->user()->id, 'verify_remote_control');

            return redirect()
                ->route('admin.students.devices.index', $student)
                ->with('error', 'Device is not remote-control ready yet. Verification was queued.');
        }

        if (! $studentDevice->remote_control_active || ! $studentDevice->remote_control_port) {
            $startCommand = $this->queueDeviceCommand($studentDevice, $request->user()->id, 'start_remote_control');
            $started = $this->waitForSuccessfulRemoteStart($studentDevice, $startCommand);

            if (! $started) {
                return redirect()
                    ->route('admin.students.devices.index', $student)
                    ->with('error', 'Remote helper did not start in time. Try again in a few seconds.');
            }
        }

        $studentDevice->remoteControlSessions()
            ->where('status', 'active')
            ->get()
            ->each(function (RemoteControlSession $existingSession) use ($gatewayService): void {
                $gatewayService->stopSession($existingSession);

                $existingSession->forceFill([
                    'status' => 'ended',
                    'ended_at' => now(),
                ])->save();
            });

        $session = RemoteControlSession::create([
            'student_device_id' => $studentDevice->id,
            'student_id' => $student->id,
            'requested_by_user_id' => $request->user()->id,
            'session_token' => Str::random(48),
            'target_host' => $studentDevice->last_ipv4,
            'status' => 'starting',
        ]);

        try {
            $gateway = $gatewayService->startSession($session, $studentDevice);

            $session->forceFill([
                'gateway_session_id' => $gateway['session_id'] ?? null,
                'viewer_path' => $gateway['viewer_path'] ?? null,
                'status' => 'active',
                'started_at' => now(),
                'failure_reason' => null,
            ])->save();
        } catch (Throwable $exception) {
            $session->forceFill([
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
                'ended_at' => now(),
            ])->save();

            return redirect()
                ->route('admin.students.devices.index', $student)
                ->with('error', 'Remote session failed: '.$exception->getMessage());
        }

        return redirect()->route('admin.remote-control-sessions.show', $session);
    }

    protected function queueDeviceCommand(StudentDevice $studentDevice, int $userId, string $commandType): DeviceCommand
    {
        return $studentDevice->commands()->create([
            'requested_by_user_id' => $userId,
            'command_type' => $commandType,
            'status' => 'pending',
            'payload' => [],
            'requested_at' => now(),
        ]);
    }

    protected function waitForSuccessfulRemoteStart(StudentDevice $studentDevice, DeviceCommand $command): bool
    {
        $timeoutSeconds = max(1, (int) config('services.remote_control.start_command_wait_seconds', 12));
        $deadline = now()->addSeconds($timeoutSeconds);

        do {
            $command->refresh();
            if ($command->status === 'completed') {
                $studentDevice->forceFill([
                    'remote_control_ready' => true,
                    'remote_control_active' => true,
                    'remote_control_port' => $studentDevice->remote_control_port ?: 5905,
                    'remote_control_last_checked_at' => now(),
                    'remote_control_failure_reason' => null,
                ])->save();

                return true;
            }

            if ($command->status === 'failed') {
                $output = (string) data_get($command->results()->first()?->payload, 'output', '');
                $studentDevice->forceFill([
                    'remote_control_last_checked_at' => now(),
                    'remote_control_failure_reason' => $output !== '' ? $output : 'remote helper failed to start',
                ])->save();

                return false;
            }

            usleep(250000);
        } while (now()->lt($deadline));

        return false;
    }

    public function show(RemoteControlSession $remoteControlSession): Response
    {
        abort_unless($remoteControlSession->requested_by_user_id === request()->user()->id || request()->user()?->isAdmin(), 403);

        $remoteControlSession->load(['student', 'device']);

        return Inertia::render('Admin/RemoteControl/Show', [
            'session' => [
                'id' => $remoteControlSession->id,
                'status' => $remoteControlSession->status,
                'viewer_url' => $remoteControlSession->viewer_path,
                'failure_reason' => $remoteControlSession->failure_reason,
                'started_at' => $remoteControlSession->started_at?->toAtomString(),
                'ended_at' => $remoteControlSession->ended_at?->toAtomString(),
                'student' => [
                    'id' => $remoteControlSession->student->id,
                    'display_name' => $remoteControlSession->student->display_name,
                ],
                'device' => [
                    'id' => $remoteControlSession->device->id,
                    'label' => $remoteControlSession->device->label,
                    'target_host' => $remoteControlSession->target_host,
                ],
            ],
        ]);
    }

    public function destroy(RemoteControlSession $remoteControlSession, RemoteControlGatewayService $gatewayService): RedirectResponse
    {
        abort_unless($remoteControlSession->requested_by_user_id === request()->user()->id || request()->user()?->isAdmin(), 403);

        $gatewayService->stopSession($remoteControlSession);

        $remoteControlSession->forceFill([
            'status' => 'ended',
            'ended_at' => now(),
        ])->save();

        return redirect()
            ->route('admin.students.devices.index', $remoteControlSession->student_id)
            ->with('success', 'Remote session ended.');
    }
}
