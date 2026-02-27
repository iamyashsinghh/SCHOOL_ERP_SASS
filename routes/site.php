<?php

use App\Http\Controllers\Site\BookListController;
use App\Http\Controllers\Site\View\AnnouncementController;
use App\Http\Controllers\Site\View\BlogController;
use App\Http\Controllers\Site\View\EventController;
use App\Http\Controllers\Site\View\GalleryController;
use App\Http\Controllers\Site\View\NewsController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('academic/book-lists/{course}', BookListController::class);

if (config('config.site.enable_site') && config('config.site.theme') != 'custom') {
    Route::get('/', [SiteController::class, 'home'])->name('site.home');
    Route::get('/pages/{slug}', [SiteController::class, 'page'])->name('site.page');
    Route::get('/pages/events/{slug}/{uuid}', EventController::class)->name('site.page.event');
    Route::get('/pages/announcements/{slug}/{uuid}', AnnouncementController::class)->name('site.page.announcement');
    Route::get('/pages/galleries/{slug}/{uuid}', GalleryController::class)->name('site.page.gallery');
}

// Blog routes
Route::prefix('pages')->group(function () {
    Route::get('/b/{slug}/category/{category}', [SiteController::class, 'page'])->name('site.page.blog-list-category');
    Route::get('/b/{slug}/tag/{tag}', [SiteController::class, 'page'])->name('site.page.blog-list-tag');
    Route::get('/b/{slug}/{blog}', BlogController::class)->name('site.page.blog');
});

// News routes
Route::prefix('pages')->group(function () {
    Route::get('/n/{slug}/category/{category}', [SiteController::class, 'page'])->name('site.page.news-list-category');
    Route::get('/n/{slug}/tag/{tag}', [SiteController::class, 'page'])->name('site.page.news-list-tag');
    Route::get('/n/{slug}/{news}', NewsController::class)->name('site.page.news');
});
