<?php

use App\Http\Controllers\Task\ChecklistActionController;
use App\Http\Controllers\Task\ChecklistController;
use App\Http\Controllers\Task\DashboardController;
use App\Http\Controllers\Task\MemberController;
use App\Http\Controllers\Task\TaskActionController;
use App\Http\Controllers\Task\TaskController;
use Illuminate\Support\Facades\Route;

// Task Routes

Route::post('tasks/{task}/tags', [TaskActionController::class, 'updateTags'])->name('tasks.tags');
Route::post('tasks/{task}/favorite', [TaskActionController::class, 'toggleFavorite'])->name('tasks.favorite');
Route::post('tasks/{task}/status', [TaskActionController::class, 'updateStatus'])->name('tasks.status');
Route::post('tasks/{task}/media', [TaskActionController::class, 'uploadMedia'])->name('tasks.uploadMedia');
Route::delete('tasks/{task}/media/{uuid}', [TaskActionController::class, 'removeMedia'])->name('tasks.removeMedia');
Route::get('tasks/{task}/repeat/pre-requisite', [TaskActionController::class, 'repeatPreRequisite'])->name('tasks.repeatPreRequisite');
Route::post('tasks/{task}/repeat', [TaskActionController::class, 'updateRepeatation'])->name('tasks.updateRepeatation');

Route::post('tasks/reorder', [TaskActionController::class, 'reorder'])->name('tasks.reorder');
Route::post('tasks/lists/move', [TaskActionController::class, 'moveList'])->name('tasks.moveList');

Route::get('tasks/pre-requisite', [TaskController::class, 'preRequisite'])->name('tasks.preRequisite');
Route::apiResource('tasks', TaskController::class);

Route::post('tasks/{task}/checklists/{checklist}/status', [ChecklistActionController::class, 'toggleStatus']);
Route::apiResource('tasks.checklists', ChecklistController::class);

Route::apiResource('tasks.members', MemberController::class);

Route::prefix('tasks/dashboard')->middleware('permission:task:read')->group(function () {
    Route::get('stat', [DashboardController::class, 'stat'])->name('tasks.dashboard.stat');
    Route::get('favorite', [DashboardController::class, 'favorite'])->name('tasks.dashboard.favorite');
    Route::get('chart', [DashboardController::class, 'chart'])->name('tasks.dashboard.chart');
    Route::get('record', [DashboardController::class, 'record'])->name('tasks.dashboard.record');
});
