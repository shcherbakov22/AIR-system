<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManageStudentDeviceRequest;
use App\Models\DeviceCommand;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Services\DevicePolicyService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentDeviceController extends Controller
{
    public function index(Student $student, DevicePolicyService $devicePolicyService): Response
    {
        $student->load([
            'user',
            'devices.commands.results',
            'devices.monitorCaptures' => fn ($query) => $query->latest('captured_at')->limit(2),
        ]);

        return Inertia::render('Admin/Students/Devices', [
            'student' => [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'username' => $student->user?->username,
            ],
            'devices' => $student->devices
                ->sortBy('label')
                ->values()
                ->map(fn (StudentDevice $device) => [
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
                    'policy' => $devicePolicyService->buildForDevice($device)['internet_policy'],
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

    public function command(ManageStudentDeviceRequest $request, Student $student, StudentDevice $studentDevice): RedirectResponse
    {
        abort_unless($studentDevice->student_id === $student->id, 404);

        $studentDevice->commands()->create([
            'requested_by_user_id' => $request->user()->id,
            'command_type' => $request->string('command_type')->toString(),
            'status' => 'pending',
            'payload' => $request->input('payload', []),
            'requested_at' => now(),
        ]);

        return redirect()
            ->route('admin.students.devices.index', $student)
            ->with('success', "Command queued for {$studentDevice->label}.");
    }
}
