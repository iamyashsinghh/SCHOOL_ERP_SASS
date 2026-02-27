<?php

use App\Http\Controllers\Custom\AdditionalFeeMismatchController;
use App\Http\Controllers\Custom\CalculationMismatchController;
use App\Http\Controllers\Custom\DuplicatePrimaryGuardianController;
use App\Http\Controllers\Custom\FeeMismatchController;
use App\Http\Controllers\Custom\FeePaymentConcessionSetController;
use App\Http\Controllers\Custom\FixAdditionalFeeMismatchController;
use App\Http\Controllers\Custom\ForceChangePasswordController;
use App\Http\Controllers\Custom\HeadWiseFeeSummaryController;
use App\Http\Controllers\Custom\InchargeEndController;
use App\Http\Controllers\Custom\MissingStudentFeeController;
use App\Http\Controllers\Custom\SiblingConcessionCheckController;
use App\Http\Controllers\Custom\SiblingGuardianController;
use App\Http\Controllers\Custom\SyncDocumentNumberController;
use App\Http\Controllers\Custom\SyncGuardianController;
use App\Http\Controllers\Custom\TransactionUserTransferController;
use App\Http\Controllers\Custom\UpdateContactCaseController;
use App\Http\Controllers\Student\PromotionController;
use App\Services\Employee\Payroll\SalaryTemplateActionService;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:sanctum', 'user.config', 'role:admin'])->group(function () {
    Route::any('force-change-password', ForceChangePasswordController::class)->name('custom.force-change-password');

    Route::get('fee-payment-mismatch', FeeMismatchController::class);
    Route::get('additional-fee-mismatch', AdditionalFeeMismatchController::class);
    Route::get('fix-additional-fee-mismatch/{uuid}', FixAdditionalFeeMismatchController::class)->name('custom.fix-additional-fee-mismatch');
    Route::get('calculation-mismatch', CalculationMismatchController::class);
    Route::get('missing-student-fee', MissingStudentFeeController::class);

    Route::get('head-wise-fee-summary', HeadWiseFeeSummaryController::class);

    Route::get('cancel-promotion', [PromotionController::class, 'cancel']);

    Route::get('fee-payment-concession-set', FeePaymentConcessionSetController::class);

    Route::get('transaction-user-transfer', TransactionUserTransferController::class);

    Route::get('employee/payroll/salary-templates/{salary_template}/recalculate', [SalaryTemplateActionService::class, 'recalculate'])
        ->name('employee.payroll.salary-templates.recalculate');

    Route::get('sync-document-number', SyncDocumentNumberController::class);

    Route::get('incharge-end', InchargeEndController::class);

    Route::get('update-contact-case', UpdateContactCaseController::class);

    Route::get('duplicate-primary-guardian', DuplicatePrimaryGuardianController::class)->name('custom.duplicate-primary-guardian');
    Route::get('sync-guardian', SyncGuardianController::class)->name('custom.sync-guardian');
    Route::get('sibling-guardian', SiblingGuardianController::class)->name('custom.sibling-guardian');
    Route::get('sibling-concession-check', SiblingConcessionCheckController::class)->name('custom.sibling-concession-check');
});
