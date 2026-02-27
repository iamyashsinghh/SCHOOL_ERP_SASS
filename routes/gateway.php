<?php

use App\Http\Controllers\PaymentGateway\BilldeskController;
use App\Http\Controllers\PaymentGateway\BillplzController;
use App\Http\Controllers\PaymentGateway\CcavenueController;
use App\Http\Controllers\PaymentGateway\HubtelController;
use App\Http\Controllers\PaymentGateway\PayzoneController;
use App\Http\Controllers\PaymentGateway\TestController;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('payment/ccavenue/status', [CcavenueController::class, 'checkStatus'])->middleware(['auth:sanctum', 'user.config', 'role:admin|accountant']);
Route::post('payment/ccavenue/response', [CcavenueController::class, 'getResponse'])
    ->withoutMiddleware([VerifyCsrfToken::class]);
Route::post('payment/ccavenue/cancel', [CcavenueController::class, 'cancel']);

Route::get('payment/billdesk/status', [BilldeskController::class, 'checkStatus'])->middleware(['auth:sanctum', 'user.config', 'role:admin|accountant']);
Route::post('payment/billdesk/response/{referenceNumber}', [BilldeskController::class, 'getResponse'])
    ->withoutMiddleware([VerifyCsrfToken::class]);
Route::post('payment/billdesk/cancel', [BilldeskController::class, 'cancel']);

Route::post('payment/payzone/response', [PayzoneController::class, 'getResponse'])
    ->withoutMiddleware([VerifyCsrfToken::class]);
Route::post('payment/payzone/cancel', [PayzoneController::class, 'cancel']);

Route::get('payment/billplz/response', [BillplzController::class, 'getResponse']);
Route::get('payment/billplz/redirect', [BillplzController::class, 'redirectUrl']);

Route::get('payment/hubtel/status', [HubtelController::class, 'checkStatus'])->middleware(['auth:sanctum', 'user.config', 'role:admin|accountant']);
Route::get('payment/hubtel/response', [HubtelController::class, 'getResponse'])
    ->withoutMiddleware([VerifyCsrfToken::class]);
Route::any('payment/hubtel/callback', [HubtelController::class, 'callback']);
Route::get('payment/hubtel/cancel', [HubtelController::class, 'getResponse']);

Route::get('payment/test/{gateway}', [TestController::class, 'initiate']);
Route::post('payment/test/{gateway}/status', [TestController::class, 'status'])
    ->withoutMiddleware([VerifyCsrfToken::class]);
