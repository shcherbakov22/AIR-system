<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompanionDeviceRequest;
use App\Http\Requests\Api\StoreCompanionCommandResultRequest;
use App\Models\DeviceCommand;
use Illuminate\Http\JsonResponse;

class CompanionCommandController extends Controller
{
    public function next(CompanionDeviceRequest $request): JsonResponse
    {
        $device = $request->device();

        $command = $device->commands()
            ->where('status', 'pending')
            ->orderBy('requested_at')
            ->orderBy('id')
            ->first();

        return response()->json([
            'accepted' => true,
            'command' => $command ? $this->payload($command) : null,
        ]);
    }

    public function acknowledge(CompanionDeviceRequest $request, DeviceCommand $deviceCommand): JsonResponse
    {
        abort_unless($deviceCommand->student_device_id === $request->device()->id, 404);

        if ($deviceCommand->status === 'pending') {
            $deviceCommand->forceFill([
                'status' => 'leased',
                'leased_at' => now(),
            ])->save();
        }

        return response()->json([
            'accepted' => true,
            'command' => $this->payload($deviceCommand->fresh()),
        ]);
    }

    public function result(StoreCompanionCommandResultRequest $request, DeviceCommand $deviceCommand): JsonResponse
    {
        abort_unless($deviceCommand->student_device_id === $request->device()->id, 404);

        $result = $deviceCommand->results()->updateOrCreate(
            [
                'student_device_id' => $request->device()->id,
            ],
            [
                'status' => $request->string('status')->toString(),
                'payload' => $request->input('payload', []),
                'received_at' => now(),
            ],
        );

        $deviceCommand->forceFill([
            'status' => $request->string('status')->toString(),
            'completed_at' => now(),
        ])->save();

        return response()->json([
            'accepted' => true,
            'result' => [
                'id' => $result->id,
                'status' => $result->status,
            ],
        ]);
    }

    protected function payload(DeviceCommand $command): array
    {
        return [
            'id' => $command->id,
            'command_type' => $command->command_type,
            'status' => $command->status,
            'payload' => $command->payload ?? [],
            'requested_at' => $command->requested_at?->toAtomString(),
            'leased_at' => $command->leased_at?->toAtomString(),
        ];
    }
}
