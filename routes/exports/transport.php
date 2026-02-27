<?php

use App\Http\Controllers\Transport\CircleExportController;
use App\Http\Controllers\Transport\FeeExportController;
use App\Http\Controllers\Transport\Report\BatchWiseRouteExportController;
use App\Http\Controllers\Transport\Report\RouteWiseStudentExportController;
use App\Http\Controllers\Transport\RouteController;
use App\Http\Controllers\Transport\RouteExportController;
use App\Http\Controllers\Transport\StoppageExportController;
use App\Http\Controllers\Transport\Vehicle\CaseRecordController;
use App\Http\Controllers\Transport\Vehicle\CaseRecordExportController;
use App\Http\Controllers\Transport\Vehicle\Config\DocumentTypeExportController;
use App\Http\Controllers\Transport\Vehicle\Config\ExpenseTypeExportController;
use App\Http\Controllers\Transport\Vehicle\DocumentController;
use App\Http\Controllers\Transport\Vehicle\DocumentExportController;
use App\Http\Controllers\Transport\Vehicle\ExpenseRecordController;
use App\Http\Controllers\Transport\Vehicle\ExpenseRecordExportController;
use App\Http\Controllers\Transport\Vehicle\FuelRecordController;
use App\Http\Controllers\Transport\Vehicle\FuelRecordExportController;
use App\Http\Controllers\Transport\Vehicle\InchargeExportController;
use App\Http\Controllers\Transport\Vehicle\ServiceRecordController;
use App\Http\Controllers\Transport\Vehicle\ServiceRecordExportController;
use App\Http\Controllers\Transport\Vehicle\TripRecordController;
use App\Http\Controllers\Transport\Vehicle\TripRecordExportController;
use App\Http\Controllers\Transport\Vehicle\VehicleExportController;
use Illuminate\Support\Facades\Route;

Route::prefix('transport')->name('transport.')->group(function () {
    Route::name('vehicle.config.')->prefix('vehicle/config')->group(function () {
        Route::get('document-types/export', DocumentTypeExportController::class)->middleware('permission:transport:config')->name('documentTypes.export');
        Route::get('expense-types/export', ExpenseTypeExportController::class)->middleware('permission:transport:config')->name('expenseTypes.export');
    });

    Route::get('stoppages/export', StoppageExportController::class)->middleware('permission:transport-stoppage:manage')->name('stoppages.export');

    Route::get('routes/{route}/export', [RouteController::class, 'export']);

    Route::get('routes/export', RouteExportController::class)->middleware('permission:transport-route:export')->name('routes.export');

    Route::get('circles/export', CircleExportController::class)->middleware('permission:transport-circle:export')->name('circles.export');

    Route::get('fees/export', FeeExportController::class)->middleware('permission:transport-fee:export')->name('fees.export');

    Route::get('vehicles/export', VehicleExportController::class)->middleware('permission:vehicle:export')->name('vehicles.export');

    Route::get('reports/batch-wise-route/export', BatchWiseRouteExportController::class)->middleware('permission:transport:report')->name('reports.batch-wise-route.export');

    Route::get('reports/route-wise-student/export', RouteWiseStudentExportController::class)->middleware('permission:transport:report')->name('reports.route-wise-student.export');
});

Route::prefix('transport/vehicle')->name('transport.vehicle.')->group(function () {

    Route::get('incharges/export', InchargeExportController::class)->middleware('permission:vehicle-incharge:export')->name('incharges.export');

    Route::get('documents/{document}/media/{uuid}', [DocumentController::class, 'downloadMedia']);

    Route::get('documents/export', DocumentExportController::class)->middleware('permission:vehicle-document:export')->name('documents.export');

    Route::get('fuel-records/{fuel_record}/media/{uuid}', [FuelRecordController::class, 'downloadMedia']);

    Route::get('fuel-records/export', FuelRecordExportController::class)->middleware('permission:vehicle-fuel-record:export')->name('fuel-records.export');

    Route::get('trip-records/{trip_record}/media/{uuid}', [TripRecordController::class, 'downloadMedia']);

    Route::get('trip-records/export', TripRecordExportController::class)->middleware('permission:vehicle-trip-record:export')->name('trip-records.export');

    Route::get('service-records/{service_record}/media/{uuid}', [ServiceRecordController::class, 'downloadMedia']);

    Route::get('service-records/export', ServiceRecordExportController::class)->middleware('permission:vehicle-service-record:export')->name('service-records.export');

    Route::get('case-records/{case_record}/media/{uuid}', [CaseRecordController::class, 'downloadMedia']);

    Route::get('case-records/export', CaseRecordExportController::class)->middleware('permission:vehicle-case-record:export')->name('case-records.export');

    Route::get('expense-records/{expense_record}/media/{uuid}', [ExpenseRecordController::class, 'downloadMedia']);

    Route::get('expense-records/export', ExpenseRecordExportController::class)->middleware('permission:vehicle-expense-record:export')->name('expense-records.export');

});
