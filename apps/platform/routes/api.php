<?php

use App\Http\Controllers\Api\CompanionActivityController;
use App\Http\Controllers\Api\CompanionCaptureController;
use App\Http\Controllers\Api\CompanionCommandController;
use App\Http\Controllers\Api\CompanionEnrollmentController;
use App\Http\Controllers\Api\CompanionHeartbeatController;
use App\Http\Controllers\Api\CompanionPolicyController;
use App\Http\Controllers\Api\EdgeClientHeartbeatController;
use App\Http\Controllers\Api\StudentMonitorCaptureController;
use Illuminate\Support\Facades\Route;

Route::post('/edge-clients/heartbeat', [EdgeClientHeartbeatController::class, 'store'])
    ->name('api.edge-clients.heartbeat');

Route::post('/student-monitor-captures/screen', [StudentMonitorCaptureController::class, 'storeScreen'])
    ->name('api.student-monitor-captures.screen');

Route::post('/student-monitor-captures/camera', [StudentMonitorCaptureController::class, 'storeCamera'])
    ->name('api.student-monitor-captures.camera');

Route::prefix('companion')->name('api.companion.')->group(function () {
    Route::post('/enroll', [CompanionEnrollmentController::class, 'store'])->name('enroll');
    Route::post('/token/renew', [CompanionEnrollmentController::class, 'renew'])->name('token.renew');
    Route::post('/revoke', [CompanionEnrollmentController::class, 'revoke'])->name('revoke');
    Route::post('/heartbeat', [CompanionHeartbeatController::class, 'store'])->name('heartbeat');
    Route::get('/policy', [CompanionPolicyController::class, 'show'])->name('policy.show');
    Route::post('/activity', [CompanionActivityController::class, 'store'])->name('activity.store');
    Route::post('/captures/screen', [CompanionCaptureController::class, 'storeScreen'])->name('captures.screen');
    Route::post('/captures/camera', [CompanionCaptureController::class, 'storeCamera'])->name('captures.camera');
    Route::get('/commands/next', [CompanionCommandController::class, 'next'])->name('commands.next');
    Route::post('/commands/{deviceCommand}/acknowledge', [CompanionCommandController::class, 'acknowledge'])->name('commands.acknowledge');
    Route::post('/commands/{deviceCommand}/result', [CompanionCommandController::class, 'result'])->name('commands.result');
});
