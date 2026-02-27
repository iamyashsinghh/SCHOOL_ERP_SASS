<?php

use App\Http\Controllers\Command\SyncRolePermissionController;
use App\Http\Controllers\Command\UpdateController;
use Illuminate\Support\Facades\Route;

Route::get('clear-cache', function () {
    \Artisan::call('optimize:clear');

    return view('index', ['message' => 'Cache cleared.']);
})->name('clear.cache');

Route::get('clear-log', function () {
    exec('rm -f '.storage_path('logs/*.log'));
    touch(storage_path('logs/laravel.log'));

    return view('index', ['message' => 'Log cleared.']);
})->name('clear.log');

Route::get('clean-activitylog/{days?}', function (int $days = 30) {
    \Artisan::call('activitylog:clean', ['--days' => $days, '--force' => true]);

    return view('index', ['message' => 'Activity log cleaned.']);
})->name('clean.activitylog')->where('days', '[0-9]+');

Route::get('clean-notification/{days?}', function (int $days = 30) {
    \Artisan::call('notification:clean', ['--days' => $days]);

    return view('index', ['message' => 'Notification cleaned.']);
})->name('clean.notification')->where('days', '[0-9]+');

Route::get('sync-locale', function () {
    \Artisan::call('sync:locale', ['--force' => true]);

    return view('index', ['message' => 'Locale synced.']);
})->name('sync.locale');

Route::get('sync-template', function () {
    \Artisan::call('sync:template', ['--force' => true]);

    return view('index', ['message' => 'Template synced.']);
})->name('sync.template');

Route::get('sync-permission', function () {
    \Artisan::call('sync:permission', ['--force' => true]);

    return view('index', ['message' => 'Permission synced.']);
})->name('sync.permission');

Route::get('migrate', function () {
    \Artisan::call('migrate', ['--force' => true]);

    return view('index', ['message' => 'Migration complete.']);
})->name('migrate');

Route::get('sync-role-permission', SyncRolePermissionController::class)->name('sync.role.permission');

Route::get('update', UpdateController::class)->name('update.app');
