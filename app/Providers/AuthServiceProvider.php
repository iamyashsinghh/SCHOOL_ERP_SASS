<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Models\Tenant\Config\Config' => 'App\Policies\Config\ConfigPolicy',
        'App\Models\Tenant\User' => 'App\Policies\UserPolicy',
        'App\Models\Tenant\Utility\Todo' => 'App\Policies\Utility\TodoPolicy',
        'App\Models\Tenant\Post\Post' => 'App\Policies\Post\PostPolicy',
        'App\Models\Tenant\Document' => 'App\Policies\DocumentPolicy',
        'App\Models\Tenant\Academic\Period' => 'App\Policies\Academic\PeriodPolicy',
        'App\Models\Tenant\Academic\Division' => 'App\Policies\Academic\DivisionPolicy',
        'App\Models\Tenant\Academic\Course' => 'App\Policies\Academic\CoursePolicy',
        'App\Models\Tenant\Academic\Batch' => 'App\Policies\Academic\BatchPolicy',
        'App\Models\Tenant\Academic\Subject' => 'App\Policies\Academic\SubjectPolicy',
        'App\Models\Tenant\Academic\CertificateTemplate' => 'App\Policies\Academic\CertificateTemplatePolicy',
        'App\Models\Tenant\Academic\Certificate' => 'App\Policies\Academic\CertificatePolicy',
        'App\Models\Tenant\Academic\ClassTiming' => 'App\Policies\Academic\ClassTimingPolicy',
        'App\Models\Tenant\Academic\Timetable' => 'App\Policies\Academic\TimetablePolicy',
        'App\Models\Tenant\Incharge' => 'App\Policies\InchargePolicy',
        'App\Models\Tenant\Finance\FeeGroup' => 'App\Policies\Finance\FeeGroupPolicy',
        'App\Models\Tenant\Finance\FeeHead' => 'App\Policies\Finance\FeeHeadPolicy',
        'App\Models\Tenant\Finance\FeeConcession' => 'App\Policies\Finance\FeeConcessionPolicy',
        'App\Models\Tenant\Finance\FeeStructure' => 'App\Policies\Finance\FeeStructurePolicy',
        'App\Models\Tenant\Finance\LedgerType' => 'App\Policies\Finance\LedgerTypePolicy',
        'App\Models\Tenant\Finance\Ledger' => 'App\Policies\Finance\LedgerPolicy',
        'App\Models\Tenant\Finance\Transaction' => 'App\Policies\Finance\TransactionPolicy',
        'App\Models\Tenant\Finance\Receipt' => 'App\Policies\Finance\ReceiptPolicy',
        'App\Models\Tenant\Transport\Route' => 'App\Policies\Transport\RoutePolicy',
        'App\Models\Tenant\Transport\Circle' => 'App\Policies\Transport\CirclePolicy',
        'App\Models\Tenant\Transport\Fee' => 'App\Policies\Transport\FeePolicy',
        'App\Models\Tenant\Transport\Vehicle\Vehicle' => 'App\Policies\Transport\Vehicle\VehiclePolicy',
        'App\Models\Tenant\Transport\Vehicle\TripRecord' => 'App\Policies\Transport\Vehicle\TripRecordPolicy',
        'App\Models\Tenant\Transport\Vehicle\FuelRecord' => 'App\Policies\Transport\Vehicle\FuelRecordPolicy',
        'App\Models\Tenant\Transport\Vehicle\ServiceRecord' => 'App\Policies\Transport\Vehicle\ServiceRecordPolicy',
        'App\Models\Tenant\Transport\Vehicle\ExpenseRecord' => 'App\Policies\Transport\Vehicle\ExpenseRecordPolicy',
        'App\Models\Tenant\Transport\Vehicle\CaseRecord' => 'App\Policies\Transport\Vehicle\CaseRecordPolicy',
        'App\Models\Tenant\Contact' => 'App\Policies\ContactPolicy',
        'App\Models\Tenant\Guardian' => 'App\Policies\GuardianPolicy',
        'App\Models\Tenant\Student\Registration' => 'App\Policies\Student\RegistrationPolicy',
        'App\Models\Tenant\Student\Student' => 'App\Policies\Student\StudentPolicy',
        'App\Models\Tenant\Employee\Department' => 'App\Policies\Employee\DepartmentPolicy',
        'App\Models\Tenant\Employee\Designation' => 'App\Policies\Employee\DesignationPolicy',
        'App\Models\Tenant\Employee\Employee' => 'App\Policies\Employee\EmployeePolicy',
        'App\Models\Tenant\Employee\Leave\Allocation' => 'App\Policies\Employee\Leave\AllocationPolicy',
        'App\Models\Tenant\Employee\Leave\Request' => 'App\Policies\Employee\Leave\RequestPolicy',
        'App\Models\Tenant\Employee\Payroll\SalaryTemplate' => 'App\Policies\Employee\Payroll\SalaryTemplatePolicy',
        'App\Models\Tenant\Employee\Payroll\SalaryStructure' => 'App\Policies\Employee\Payroll\SalaryStructurePolicy',
        'App\Models\Tenant\Employee\Payroll\Payroll' => 'App\Policies\Employee\Payroll\PayrollPolicy',
        'App\Models\Tenant\Employee\Attendance\Attendance' => 'App\Policies\Employee\Attendance\AttendancePolicy',
        'App\Models\Tenant\Employee\Attendance\WorkShift' => 'App\Policies\Employee\Attendance\WorkShiftPolicy',
        'App\Models\Tenant\Employee\Attendance\Timesheet' => 'App\Policies\Employee\Attendance\TimesheetPolicy',
        'App\Models\Tenant\Exam\Schedule' => 'App\Policies\Exam\SchedulePolicy',
        'App\Models\Tenant\Exam\OnlineExam' => 'App\Policies\Exam\OnlineExamPolicy',
        'App\Models\Tenant\Resource\Diary' => 'App\Policies\Resource\DiaryPolicy',
        'App\Models\Tenant\Resource\OnlineClass' => 'App\Policies\Resource\OnlineClassPolicy',
        'App\Models\Tenant\Resource\Assignment' => 'App\Policies\Resource\AssignmentPolicy',
        'App\Models\Tenant\Resource\LessonPlan' => 'App\Policies\Resource\LessonPlanPolicy',
        'App\Models\Tenant\Resource\LearningMaterial' => 'App\Policies\Resource\LearningMaterialPolicy',
        'App\Models\Tenant\Resource\Download' => 'App\Policies\Resource\DownloadPolicy',
        'App\Models\Tenant\Resource\Syllabus' => 'App\Policies\Resource\SyllabusPolicy',
        'App\Models\Tenant\Calendar\Holiday' => 'App\Policies\Calendar\HolidayPolicy',
        'App\Models\Tenant\Calendar\Event' => 'App\Policies\Calendar\EventPolicy',
        'App\Models\Tenant\Reception\Enquiry' => 'App\Policies\Reception\EnquiryPolicy',
        'App\Models\Tenant\Reception\VisitorLog' => 'App\Policies\Reception\VisitorLogPolicy',
        'App\Models\Tenant\Reception\GatePass' => 'App\Policies\Reception\GatePassPolicy',
        'App\Models\Tenant\Reception\Complaint' => 'App\Policies\Reception\ComplaintPolicy',
        'App\Models\Tenant\Reception\Query' => 'App\Policies\Reception\QueryPolicy',
        'App\Models\Tenant\Reception\CallLog' => 'App\Policies\Reception\CallLogPolicy',
        'App\Models\Tenant\Reception\Correspondence' => 'App\Policies\Reception\CorrespondencePolicy',
        'App\Models\Tenant\Library\Book' => 'App\Policies\Library\BookPolicy',
        'App\Models\Tenant\Library\BookAddition' => 'App\Policies\Library\BookAdditionPolicy',
        'App\Policies\Blog\Blog' => 'App\Policies\Blog\BlogPolicy',
        'App\Policies\News\News' => 'App\Policies\News\NewsPolicy',
        'App\Models\Tenant\Approval\Request' => 'App\Policies\Approval\RequestPolicy',
        'App\Models\Tenant\Task\Task' => 'App\Policies\Task\TaskPolicy',
        'App\Models\Tenant\Helpdesk\Faq\Faq' => 'App\Policies\Helpdesk\Faq\FaqPolicy',
        'App\Models\Tenant\Helpdesk\Ticket\Ticket' => 'App\Policies\Helpdesk\Ticket\TicketPolicy',
        'App\Models\Tenant\Mess\MealLog' => 'App\Policies\Mess\MealLogPolicy',
        'App\Models\Tenant\Inventory\Vendor' => 'App\Policies\Inventory\VendorPolicy',
        'App\Models\Tenant\Inventory\StockCategory' => 'App\Policies\Inventory\StockCategoryPolicy',
        'App\Models\Tenant\Inventory\StockItem' => 'App\Policies\Inventory\StockItemPolicy',
        'App\Models\Tenant\Inventory\StockRequisition' => 'App\Policies\Inventory\StockRequisitionPolicy',
        'App\Models\Tenant\Inventory\StockPurchase' => 'App\Policies\Inventory\StockPurchasePolicy',
        'App\Models\Tenant\Inventory\StockReturn' => 'App\Policies\Inventory\StockReturnPolicy',
        'App\Models\Tenant\Inventory\StockAdjustment' => 'App\Policies\Inventory\StockAdjustmentPolicy',
        'App\Models\Tenant\Inventory\StockTransfer' => 'App\Policies\Inventory\StockTransferPolicy',
        'App\Models\Tenant\Communication\Communication' => 'App\Policies\Communication\CommunicationPolicy',
        'App\Models\Tenant\Communication\Announcement' => 'App\Policies\Communication\AnnouncementPolicy',
        'App\Models\Tenant\Hostel\RoomAllocation' => 'App\Policies\Hostel\RoomAllocationPolicy',
        'App\Models\Tenant\Recruitment\Vacancy' => 'App\Policies\Recruitment\VacancyPolicy',
        'App\Models\Tenant\Recruitment\Application' => 'App\Policies\Recruitment\ApplicationPolicy',
        'App\Models\Tenant\Form\Form' => 'App\Policies\Form\FormPolicy',
        'App\Models\Tenant\Gallery' => 'App\Policies\GalleryPolicy',
        'App\Models\Tenant\Activity\Trip' => 'App\Policies\Activity\TripPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        Gate::before(function ($user, $ability) {
            return ($user->is_default || $user->hasRole('admin')) ? true : null;
        });

        // Gate::after(function ($user, $ability) {
        //     return $user->hasRole('admin');
        // });
    }
}
