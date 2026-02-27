<?php

use App\Http\Controllers\Transport\CircleController;
use App\Http\Controllers\Transport\FeeController;
use App\Http\Controllers\Transport\Report\BatchWiseRouteController;
use App\Http\Controllers\Transport\Report\RouteWiseStudentController;
use App\Http\Controllers\Transport\RouteActionController;
use App\Http\Controllers\Transport\RouteController;
use App\Http\Controllers\Transport\StoppageController;
use App\Http\Controllers\Transport\StoppageImportController;
use App\Http\Controllers\Transport\Vehicle\CaseRecordController;
use App\Http\Controllers\Transport\Vehicle\Config\DocumentTypeController;
use App\Http\Controllers\Transport\Vehicle\Config\ExpenseTypeController;
use App\Http\Controllers\Transport\Vehicle\DocumentController;
use App\Http\Controllers\Transport\Vehicle\DocumentImportController;
use App\Http\Controllers\Transport\Vehicle\ExpenseRecordController;
use App\Http\Controllers\Transport\Vehicle\ExpenseRecordImportController;
use App\Http\Controllers\Transport\Vehicle\FuelRecordController;
use App\Http\Controllers\Transport\Vehicle\InchargeController;
use App\Http\Controllers\Transport\Vehicle\InchargeImportController;
use App\Http\Controllers\Transport\Vehicle\ServiceRecordController;
use App\Http\Controllers\Transport\Vehicle\TripRecordController;
use App\Http\Controllers\Transport\Vehicle\VehicleController;
use App\Http\Controllers\Transport\Vehicle\VehicleImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('transport')->name('transport.')->group(function () {
    Route::name('vehicle.config.')->prefix('vehicle/config')->group(function () {
        Route::apiResource('document-types', DocumentTypeController::class)->middleware('permission:transport:config')->names('documentTypes');
        Route::apiResource('expense-types', ExpenseTypeController::class)->middleware('permission:transport:config')->names('expenseTypes');
    });

    Route::get('stoppages/pre-requisite', [StoppageController::class, 'preRequisite'])->name('stoppages.preRequisite')->middleware('permission:transport-stoppage:manage');

    Route::post('stoppages/import', StoppageImportController::class)->middleware('permission:transport-stoppage:manage');
    Route::apiResource('stoppages', StoppageController::class)->middleware('permission:transport-stoppage:manage');

    Route::get('routes/pre-requisite', [RouteController::class, 'preRequisite'])->name('routes.preRequisite');

    Route::delete('routes/{route}/passengers/{passenger}', [RouteActionController::class, 'removePassenger'])->name('routes.removePassenger');
    Route::post('routes/{route}/students', [RouteActionController::class, 'addStudent'])->name('routes.addStudent');
    Route::post('routes/{route}/employees', [RouteActionController::class, 'addEmployee'])->name('routes.addEmployee');

    Route::apiResource('routes', RouteController::class);

    Route::get('circles/pre-requisite', [CircleController::class, 'preRequisite'])->name('circles.preRequisite');

    Route::apiResource('circles', CircleController::class);

    Route::get('fees/pre-requisite', [FeeController::class, 'preRequisite'])->name('fees.preRequisite');

    Route::apiResource('fees', FeeController::class);

    Route::get('vehicles/pre-requisite', [VehicleController::class, 'preRequisite'])->name('vehicles.preRequisite');

    Route::post('vehicles/import', VehicleImportController::class)->middleware('permission:vehicle:create');

    Route::apiResource('vehicles', VehicleController::class);

    Route::prefix('reports')->name('reports.')->middleware('permission:transport:report')->group(function () {
        Route::get('batch-wise-route/pre-requisite', [BatchWiseRouteController::class, 'preRequisite'])->name('batch-wise-route.preRequisite');
        Route::get('batch-wise-route', [BatchWiseRouteController::class, 'fetch'])->name('batch-wise-route.fetch');

        Route::get('route-wise-student/pre-requisite', [RouteWiseStudentController::class, 'preRequisite'])->name('route-wise-student.preRequisite');
        Route::get('route-wise-student', [RouteWiseStudentController::class, 'fetch'])->name('route-wise-student.fetch');
    });
});

Route::prefix('transport/vehicle')->name('transport.vehicle.')->group(function () {

    Route::get('incharges/pre-requisite', [InchargeController::class, 'preRequisite'])->name('incharges.preRequisite');

    Route::post('incharges/import', InchargeImportController::class)->middleware('permission:vehicle-incharge:create');

    Route::apiResource('incharges', InchargeController::class);

    Route::get('documents/pre-requisite', [DocumentController::class, 'preRequisite'])->name('documents.preRequisite');

    Route::post('documents/import', DocumentImportController::class)->middleware('permission:vehicle-document:create')->name('documents.import');

    Route::apiResource('documents', DocumentController::class);

    Route::get('fuel-records/pre-requisite', [FuelRecordController::class, 'preRequisite'])->name('fuel-records.preRequisite');

    Route::post('fuel-records/previous-log', [FuelRecordController::class, 'getPreviousLog'])->name('fuel-records.getPreviousLog');
    Route::apiResource('fuel-records', FuelRecordController::class);

    Route::get('trip-records/pre-requisite', [TripRecordController::class, 'preRequisite'])->name('trip-records.preRequisite');

    Route::apiResource('trip-records', TripRecordController::class);

    Route::get('service-records/pre-requisite', [ServiceRecordController::class, 'preRequisite'])->name('service-records.preRequisite');

    Route::apiResource('service-records', ServiceRecordController::class);

    Route::get('case-records/pre-requisite', [CaseRecordController::class, 'preRequisite'])->name('case-records.preRequisite');

    Route::apiResource('case-records', CaseRecordController::class);

    Route::get('expense-records/pre-requisite', [ExpenseRecordController::class, 'preRequisite'])->name('expense-records.preRequisite');

    Route::post('expense-records/import', ExpenseRecordImportController::class)->middleware('permission:vehicle-expense-record:create')->name('expense-records.import');

    Route::apiResource('expense-records', ExpenseRecordController::class);
});
