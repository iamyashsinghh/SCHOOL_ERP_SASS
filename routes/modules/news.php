<?php

use App\Http\Controllers\News\NewsActionController;
use App\Http\Controllers\News\NewsController;
use Illuminate\Support\Facades\Route;

Route::prefix('news')->group(function () {
    Route::get('pre-requisite', [NewsController::class, 'preRequisite'])->name('news.preRequisite');

    Route::post('{news}/assets/{type}', [NewsActionController::class, 'uploadAsset'])->name('news.uploadAsset')->whereIn('type', ['cover', 'og']);
    Route::delete('{news}/assets/{type}', [NewsActionController::class, 'removeAsset'])->name('news.removeAsset')->whereIn('type', ['cover', 'og']);

    Route::post('{news}/meta', [NewsActionController::class, 'updateMeta'])->name('news.updateMeta');

    Route::post('{news}/archive', [NewsActionController::class, 'archive'])->name('news.archive');
    Route::post('{news}/unarchive', [NewsActionController::class, 'unarchive'])->name('news.unarchive');

    Route::post('{news}/pin', [NewsActionController::class, 'pin'])->name('news.pin');
    Route::post('{news}/unpin', [NewsActionController::class, 'unpin'])->name('news.unpin');

    Route::post('archive', [NewsController::class, 'archiveMultiple'])->name('news.archiveMultiple');
    Route::post('unarchive', [NewsController::class, 'unarchiveMultiple'])->name('news.unarchiveMultiple');
    Route::post('delete', [NewsController::class, 'destroyMultiple'])->name('news.destroyMultiple');
});

Route::apiResource('news', NewsController::class);
