<?php

use App\Http\Controllers\Task\ChecklistExportController;
use App\Http\Controllers\Task\TaskController;
use App\Http\Controllers\Task\TaskExportController;
use Illuminate\Support\Facades\Route;

Route::get('tasks/{task}/media/{uuid}', [TaskController::class, 'downloadMedia']);
Route::get('tasks/export', TaskExportController::class)->middleware('permission:task:export');

Route::get('tasks/{task}/checklists/export', ChecklistExportController::class)->middleware('permission:task:export');
