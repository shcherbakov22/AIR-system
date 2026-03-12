<?php

use App\Http\Controllers\Api\EdgeClientHeartbeatController;
use App\Http\Controllers\Api\StudentMonitorCaptureController;
use Illuminate\Support\Facades\Route;

Route::post('/edge-clients/heartbeat', [EdgeClientHeartbeatController::class, 'store'])
    ->name('api.edge-clients.heartbeat');

Route::post('/student-monitor-captures/screen', [StudentMonitorCaptureController::class, 'storeScreen'])
    ->name('api.student-monitor-captures.screen');

Route::post('/student-monitor-captures/camera', [StudentMonitorCaptureController::class, 'storeCamera'])
    ->name('api.student-monitor-captures.camera');
