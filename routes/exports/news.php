<?php

use App\Http\Controllers\News\NewsController;
use App\Http\Controllers\News\NewsExportController;
use Illuminate\Support\Facades\Route;

Route::get('news/{news}/media/{uuid}', [NewsController::class, 'downloadMedia']);
Route::get('news/export', NewsExportController::class)
    ->middleware('permission:news:read')
    ->name('news.export');
