<?php

use App\Http\Controllers\Helpdesk\Faq\FaqExportController;
use App\Http\Controllers\Helpdesk\Ticket\TicketController;
use App\Http\Controllers\Helpdesk\Ticket\TicketExportController;
use Illuminate\Support\Facades\Route;

Route::prefix('helpdesk')->group(function () {
    Route::get('faqs/export', FaqExportController::class)->middleware('permission:faq:export')->name('faqs.export');

    Route::get('tickets/export', TicketExportController::class)->middleware('permission:ticket:export')->name('tickets.export');

    Route::get('tickets/{ticket}/media/{uuid}', [TicketController::class, 'downloadMedia']);
    Route::get('tickets/{ticket}/messages/{message}/media/{uuid}', [TicketController::class, 'downloadMessageMedia']);
});
