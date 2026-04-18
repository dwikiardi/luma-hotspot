<?php

use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->group(function () {
    Route::get('{tenantId}/summary', [DashboardController::class, 'summary']);
    Route::get('{tenantId}/complaints', [DashboardController::class, 'complaints']);
    Route::get('{tenantId}/roi', [DashboardController::class, 'roi']);
    Route::get('{tenantId}/visitors', [DashboardController::class, 'visitors']);
});
