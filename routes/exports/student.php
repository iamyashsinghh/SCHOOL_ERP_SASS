<?php

use App\Http\Controllers\Student\AccountController;
use App\Http\Controllers\Student\AccountExportController;
use App\Http\Controllers\Student\AccountsController;
use App\Http\Controllers\Student\AccountsExportController;
use App\Http\Controllers\Student\AttendanceExportController;
use App\Http\Controllers\Student\Config\DocumentTypeExportController;
use App\Http\Controllers\Student\CustomFeeExportController;
use App\Http\Controllers\Student\DialogueController;
use App\Http\Controllers\Student\DialogueExportController;
use App\Http\Controllers\Student\DocumentController;
use App\Http\Controllers\Student\DocumentExportController;
use App\Http\Controllers\Student\DocumentsController;
use App\Http\Controllers\Student\DocumentsExportController;
use App\Http\Controllers\Student\EditRequestController;
use App\Http\Controllers\Student\EditRequestExportController;
use App\Http\Controllers\Student\FeeController;
use App\Http\Controllers\Student\FeeRefundController;
use App\Http\Controllers\Student\FeeRefundExportController;
use App\Http\Controllers\Student\GuardianExportController;
use App\Http\Controllers\Student\LeaveRequestController;
use App\Http\Controllers\Student\LeaveRequestExportController;
use App\Http\Controllers\Student\PaymentController;
use App\Http\Controllers\Student\ProfileEditRequestController;
use App\Http\Controllers\Student\PromotionExportController;
use App\Http\Controllers\Student\QualificationController;
use App\Http\Controllers\Student\QualificationExportController;
use App\Http\Controllers\Student\QualificationsController;
use App\Http\Controllers\Student\QualificationsExportController;
use App\Http\Controllers\Student\RecordExportController;
use App\Http\Controllers\Student\RegistrationController;
use App\Http\Controllers\Student\RegistrationDocumentController;
use App\Http\Controllers\Student\RegistrationExportController;
use App\Http\Controllers\Student\RegistrationPaymentController;
use App\Http\Controllers\Student\RegistrationQualificationController;
use App\Http\Controllers\Student\Report\BatchWiseAttendanceExportController;
use App\Http\Controllers\Student\Report\DailyAccessReportExportController;
use App\Http\Controllers\Student\Report\DateWiseAttendanceExportController;
use App\Http\Controllers\Student\Report\SubjectWiseAttendanceExportController;
use App\Http\Controllers\Student\Report\SubjectWiseStudentController;
use App\Http\Controllers\Student\ServiceRequestController;
use App\Http\Controllers\Student\ServiceRequestExportController;
use App\Http\Controllers\Student\StudentController;
use App\Http\Controllers\Student\StudentExportController;
use App\Http\Controllers\Student\TimesheetExportController;
use App\Http\Controllers\Student\TransferController;
use App\Http\Controllers\Student\TransferExportController;
use App\Http\Controllers\Student\TransferRequestController;
use App\Http\Controllers\Student\TransferRequestExportController;
use Illuminate\Support\Facades\Route;

Route::name('student.')->prefix('student')->group(function () {
    Route::name('config.')->prefix('config')->group(function () {
        Route::get('document-types/export', DocumentTypeExportController::class)->middleware('permission:student:config')->name('documentTypes.export');
    });

    Route::get('registrations/{registration}/qualifications/{qualification}/media/{uuid}', [RegistrationQualificationController::class, 'downloadMedia']);
    Route::get('registrations/{registration}/documents/{document}/media/{uuid}', [RegistrationDocumentController::class, 'downloadMedia']);

    Route::get('registrations/{registration}/media/{uuid}', [RegistrationController::class, 'downloadMedia']);
    Route::get('registrations/{registration}/export', [RegistrationController::class, 'export']);
    Route::get('registrations/export', RegistrationExportController::class)->middleware('permission:registration:export')->name('registrations.export');

    Route::get('registrations/{registration}/fee/{uuid}/export', [RegistrationPaymentController::class, 'exportFee']);

    Route::get('edit-requests/{edit_request}/media/{uuid}', [EditRequestController::class, 'downloadMedia']);
    Route::get('edit-requests/export', EditRequestExportController::class)->middleware('permission:student:edit-request-action')->name('edit-requests.export');

    Route::get('service-requests/{service_request}/media/{uuid}', [ServiceRequestController::class, 'downloadMedia']);
    Route::get('service-requests/export', ServiceRequestExportController::class)->middleware('permission:student:service-request')->name('service-requests.export');

    Route::get('leave-requests/{leave_request}/media/{uuid}', [LeaveRequestController::class, 'downloadMedia']);
    Route::get('leave-requests/export', LeaveRequestExportController::class)->middleware('permission:student:leave-request')->name('leave-requests.export');

    Route::get('transfer-requests/{transfer_request}/media/{uuid}', [TransferRequestController::class, 'downloadMedia']);
    Route::get('transfer-requests/export', TransferRequestExportController::class)->middleware('permission:student:transfer-request')->name('transfer-requests.export');

    Route::get('promotion/export', PromotionExportController::class)->middleware('permission:student:promotion')->name('promotions.export');

    Route::get('transfers/{student}/media/{uuid}', [TransferController::class, 'downloadMedia']);

    Route::get('transfers/export', TransferExportController::class)->middleware('permission:student:transfer')->name('transfers.export');

    Route::get('attendance/export', AttendanceExportController::class)->middleware('permission:student:list-attendance')->name('attendances.export');

    Route::get('timesheets/export', TimesheetExportController::class)->middleware('permission:student:manage-timesheet');

    Route::get('documents/export', DocumentsExportController::class)->middleware('permission:student:export')->name('documents.export');
    Route::get('documents/{document}/media/{uuid}', [DocumentsController::class, 'downloadMedia']);

    Route::get('accounts/export', AccountsExportController::class)->middleware('permission:student:export')->name('accounts.export');
    Route::get('accounts/{account}/media/{uuid}', [AccountsController::class, 'downloadMedia']);

    Route::get('qualifications/export', QualificationsExportController::class)->middleware('permission:student:export')->name('qualifications.export');
    Route::get('qualifications/{qualification}/media/{uuid}', [QualificationsController::class, 'downloadMedia']);

    Route::get('reports/date-wise-attendance/export', DateWiseAttendanceExportController::class)->middleware('permission:student:list-attendance')->name('reports.date-wise-attendance.export');

    Route::get('reports/batch-wise-attendance/export', BatchWiseAttendanceExportController::class)->middleware('permission:student:list-attendance')->name('reports.batch-wise-attendance.export');

    Route::get('reports/subject-wise-attendance/export', SubjectWiseAttendanceExportController::class)->middleware('permission:student:list-attendance')->name('reports.subject-wise-attendance.export');

    Route::get('reports/subject-wise-student/export', [SubjectWiseStudentController::class, 'export'])->middleware('permission:student:report')->name('reports.subject-wise-student.export');

    Route::get('reports/daily-access-report/export', DailyAccessReportExportController::class)->middleware('permission:student:report')->name('reports.daily-access-report.export');
});

Route::get('students/{student}/transactions/{transaction}/export', [PaymentController::class, 'export'])->name('students.transactions.export');

Route::get('students/{student}/transactions', [PaymentController::class, 'exportAll'])->name('students.transactions.exportAll');

Route::get('students/{student}/export', [StudentController::class, 'export']);

Route::get('students/{student}/fee/export', [FeeController::class, 'exportFee'])->name('students.fee.export');

Route::get('students/{student}/fee-groups/{feeGroup}/export', [FeeController::class, 'exportFeeGroup'])->name('students.fee-groups.export');

Route::get('students/{student}/installments/{installment}/export', [FeeController::class, 'exportFeeInstallment'])->name('students.fee-installments.export');

Route::get('students/{student}/custom-fees/export', CustomFeeExportController::class)->middleware('permission:student:export')->name('students.custom-fees.export');

Route::get('students/{student}/fee-refunds/export', FeeRefundExportController::class)->middleware('permission:student:export')->name('students.fee-refunds.export');

Route::get('students/{student}/fee-refunds/{transaction}/export', [FeeRefundController::class, 'export'])->name('students.fee-refund.export');

Route::get('students/{student}/dialogues/{dialogue}/media/{uuid}', [DialogueController::class, 'downloadMedia'])->middleware('permission:student:dialogue');
Route::get('students/{student}/dialogues/export', DialogueExportController::class)->middleware('permission:student:dialogue')->name('students.dialogues.export');

Route::get('students/{student}/accounts/{account}/media/{uuid}', [AccountController::class, 'downloadMedia']);
Route::get('students/{student}/accounts/export', AccountExportController::class)->middleware('permission:student:export')->name('students.accounts.export');

Route::get('students/{student}/documents/{document}/media/{uuid}', [DocumentController::class, 'downloadMedia']);
Route::get('students/{student}/documents/export', DocumentExportController::class)->middleware('permission:student:export')->name('students.documents.export');

Route::get('students/{student}/qualifications/{qualification}/media/{uuid}', [QualificationController::class, 'downloadMedia']);
Route::get('students/{student}/qualifications/export', QualificationExportController::class)->middleware('permission:student:export')->name('students.qualifications.export');

Route::get('students/{student}/guardians/export', GuardianExportController::class)->middleware('permission:student:export')->name('students.guardians.export');

Route::get('students/{student}/records/export', RecordExportController::class)->middleware('permission:student:export')->name('students.records.export');

Route::get('students/{student}/edit-requests/{edit_request}/media/{uuid}', [ProfileEditRequestController::class, 'downloadMedia']);

Route::get('students/export', StudentExportController::class)->middleware('permission:student:export')->name('students.export');
