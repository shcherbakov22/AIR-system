<?php

use App\Http\Controllers\Api\CompanionActivityController;
use App\Http\Controllers\Api\CompanionAttentionCalibrationController;
use App\Http\Controllers\Api\CompanionAttentionEventController;
use App\Http\Controllers\Api\CompanionBrowserPolicyController;
use App\Http\Controllers\Api\CompanionCaptureController;
use App\Http\Controllers\Api\CompanionCommandController;
use App\Http\Controllers\Api\CompanionEnrollmentController;
use App\Http\Controllers\Api\CompanionHeartbeatController;
use App\Http\Controllers\Api\CompanionPolicyController;
use App\Http\Controllers\Api\CompanionPushUpStationController;
use App\Http\Controllers\Api\CompanionUpdateController;
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
    Route::get('/update-manifest', [CompanionUpdateController::class, 'manifest'])->name('update.manifest');
    Route::get('/downloads/windows/latest', [CompanionUpdateController::class, 'download'])->name('update.download');
    Route::post('/enroll', [CompanionEnrollmentController::class, 'store'])->name('enroll');
    Route::post('/enroll/claim', [CompanionEnrollmentController::class, 'claim'])->name('enroll.claim');
    Route::post('/token/renew', [CompanionEnrollmentController::class, 'renew'])->name('token.renew');
    Route::post('/browser-login', [CompanionEnrollmentController::class, 'browserLogin'])->name('browser-login');
    Route::post('/revoke', [CompanionEnrollmentController::class, 'revoke'])->name('revoke');
    Route::post('/heartbeat', [CompanionHeartbeatController::class, 'store'])->name('heartbeat');
    Route::get('/policy', [CompanionPolicyController::class, 'show'])->name('policy.show');
    Route::get('/browser/policy', [CompanionBrowserPolicyController::class, 'show'])->name('browser.policy.show');
    Route::post('/browser/visits', [CompanionBrowserPolicyController::class, 'visit'])->name('browser.visits.store');
    Route::post('/browser/access-requests', [CompanionBrowserPolicyController::class, 'requestAccess'])->name('browser.access-requests.store');
    Route::post('/activity', [CompanionActivityController::class, 'store'])->name('activity.store');
    Route::get('/attention/status', [CompanionAttentionCalibrationController::class, 'status'])->name('attention.status');
    Route::post('/attention/sessions', [CompanionAttentionCalibrationController::class, 'start'])->name('attention.sessions.start');
    Route::post('/attention/sessions/{sessionUuid}/batches', [CompanionAttentionCalibrationController::class, 'storeBatch'])->name('attention.sessions.batches.store');
    Route::post('/attention/events', [CompanionAttentionEventController::class, 'store'])->name('attention.events.store');
    Route::post('/captures/screen', [CompanionCaptureController::class, 'storeScreen'])->name('captures.screen');
    Route::post('/captures/camera', [CompanionCaptureController::class, 'storeCamera'])->name('captures.camera');
    Route::post('/push-up-station/heartbeat', [CompanionPushUpStationController::class, 'heartbeat'])->name('push-up-station.heartbeat');
    Route::post('/push-up-station/claim-next', [CompanionPushUpStationController::class, 'claimNext'])->name('push-up-station.claim-next');
    Route::post('/push-up-station/sessions/{pushUpSession}/start', [CompanionPushUpStationController::class, 'start'])->name('push-up-station.sessions.start');
    Route::post('/push-up-station/sessions/{pushUpSession}/progress', [CompanionPushUpStationController::class, 'progress'])->name('push-up-station.sessions.progress');
    Route::post('/push-up-station/sessions/{pushUpSession}/complete', [CompanionPushUpStationController::class, 'complete'])->name('push-up-station.sessions.complete');
    Route::post('/push-up-station/sessions/{pushUpSession}/fail', [CompanionPushUpStationController::class, 'fail'])->name('push-up-station.sessions.fail');
    Route::get('/commands/next', [CompanionCommandController::class, 'next'])->name('commands.next');
    Route::post('/commands/{deviceCommand}/acknowledge', [CompanionCommandController::class, 'acknowledge'])->name('commands.acknowledge');
    Route::post('/commands/{deviceCommand}/result', [CompanionCommandController::class, 'result'])->name('commands.result');
});
