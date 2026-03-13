<?php

use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('login', [MobileAuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'mobile.context'])->group(function () {
    Route::post('auth/logout', [MobileAuthController::class, 'logout']);
    Route::get('auth/profile', [MobileAuthController::class, 'profile']);

    Route::prefix('dashboard')->group(function () {
        Route::get('stats', [MobileDashboardController::class, 'stats']);
    });

    Route::prefix('academic')->group(function () {
        Route::get('courses', [\App\Http\Controllers\Api\Mobile\MobileAcademicController::class, 'courses']);
        Route::get('batches', [\App\Http\Controllers\Api\Mobile\MobileAcademicController::class, 'batches']);
        Route::get('subjects', [\App\Http\Controllers\Api\Mobile\MobileAcademicController::class, 'subjects']);
        Route::get('timetable', [\App\Http\Controllers\Api\Mobile\MobileAcademicController::class, 'timetable']);
    });

    Route::prefix('students')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Mobile\MobileStudentController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Mobile\MobileStudentController::class, 'show']);
        Route::get('/{id}/attendance', [\App\Http\Controllers\Api\Mobile\MobileStudentController::class, 'attendance']);
        Route::get('/{id}/fees', [\App\Http\Controllers\Api\Mobile\MobileStudentController::class, 'fees']);
    });
});
