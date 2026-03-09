<?php

use App\Http\Controllers\Api\EdgeClientHeartbeatController;
use Illuminate\Support\Facades\Route;

Route::post('/edge-clients/heartbeat', [EdgeClientHeartbeatController::class, 'store'])
    ->name('api.edge-clients.heartbeat');
