<?php

use App\Http\Controllers\Helpdesk\Faq\FaqController;
use App\Http\Controllers\Helpdesk\Ticket\TicketActionController;
use App\Http\Controllers\Helpdesk\Ticket\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('helpdesk')->group(function () {
    Route::get('faqs/pre-requisite', [FaqController::class, 'preRequisite'])->name('faqs.preRequisite');
    Route::post('faqs/delete', [FaqController::class, 'destroyMultiple'])->name('faqs.destroyMultiple');
    Route::apiResource('faqs', FaqController::class);

    Route::get('tickets/pre-requisite', [TicketController::class, 'preRequisite'])->name('tickets.preRequisite');
    Route::post('tickets/delete', [TicketController::class, 'destroyMultiple'])->name('tickets.destroyMultiple');
    Route::post('tickets/{ticket}/assign', [TicketActionController::class, 'assign']);
    Route::post('tickets/{ticket}/unassign/{employee}', [TicketActionController::class, 'unassign']);
    Route::post('tickets/assign', [TicketActionController::class, 'updateBulkAssignTo'])->name('tickets.updateBulkAssignTo');
    Route::post('tickets/category', [TicketActionController::class, 'updateBulkCategory'])->name('tickets.updateBulkCategory');
    Route::post('tickets/priority', [TicketActionController::class, 'updateBulkPriority'])->name('tickets.updateBulkPriority');
    Route::post('tickets/{ticket}/messages', [TicketActionController::class, 'addMessage']);
    Route::post('tickets/{ticket}/messages/{message}', [TicketActionController::class, 'removeMessage']);

    Route::apiResource('tickets', TicketController::class);
});
