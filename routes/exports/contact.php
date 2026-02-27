<?php

use App\Http\Controllers\Contact\Config\DocumentTypeExportController;
use App\Http\Controllers\ContactExportController;
use Illuminate\Support\Facades\Route;

Route::name('contact.config.')->prefix('contact/config')->group(function () {
    Route::get('document-types/export', DocumentTypeExportController::class)->middleware('permission:contact:config')->name('documentTypes.export');
});

Route::get('contacts/export', ContactExportController::class)->middleware('permission:contact:export')->name('contacts.export');
