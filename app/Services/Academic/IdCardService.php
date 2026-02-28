<?php

namespace App\Services\Academic;

use App\Actions\Employee\FetchEmployee;
use App\Actions\Student\FetchBatchWiseStudent;
use App\Concerns\IdCardTemplateParser;
use App\Enums\Employee\Type as EmployeeType;
use App\Enums\ServiceRequestType;
use App\Enums\ServiceType;
use App\Models\Tenant\Academic\Batch;
use App\Models\Tenant\Academic\IdCardTemplate;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\Student\Fee;
use App\Models\Tenant\Student\Student;
use App\Models\Tenant\Transport\RoutePassenger;
use chillerlan\QRCode\QRCode;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Milon\Barcode\DNS1D;

class IdCardService
{
    use IdCardTemplateParser;

    private function getPredefinedTemplates()
    {
        $predefinedTemplates = collect(glob(resource_path('views/print/academic/id-card/*.blade.php')))
            ->filter(function ($template) {
                return Str::contains(basename($template), ['-student', '-employee', '-guardian']);
            })
            ->map(function ($template) {
                if (Str::contains(basename($template), 'student')) {
                    $for = 'student';
                } elseif (Str::contains(basename($template), 'employee')) {
                    $for = 'employee';
                } elseif (Str::contains(basename($template), 'guardian')) {
                    $for = 'guardian';
                } else {
                    $for = 'other';
                }

                return [
                    'name' => basename($template, '.blade.php'),
                    'for' => $for,
                    'type' => 'predefined',
                ];
            });

        return $predefinedTemplates;
    }

    private function getCustomTemplates()
    {
        $idCardTemplates = IdCardTemplate::query()
            ->byTeam()
            ->get();

        $customTemplates = collect(glob(resource_path('views/print/custom/academic/id-card/templates/*.blade.php')))
            ->filter(function ($template) {
                return ! in_array(basename($template), ['index.blade.php']);
            })
            ->map(function ($template) use ($idCardTemplates) {
                $idCardTemplate = $idCardTemplates->firstWhere('config.custom_template_file_name', basename($template, '.blade.php'));

                return [
                    'name' => basename($template, '.blade.php'),
                    'for' => $idCardTemplate?->for?->value ?? 'other',
                    'type' => 'custom',
                ];
            });

        return $customTemplates;
    }

    public function preRequisite(Request $request)
    {
        $predefinedTemplates = $this->getPredefinedTemplates();

        $idCardTemplateConfigs = collect(Arr::getVar('id-card-templates'));

        $customTemplates = $this->getCustomTemplates();

        $templates = collect($predefinedTemplates->merge($customTemplates))
            ->unique()
            ->map(function ($template) use ($idCardTemplateConfigs) {

                $templateConfig = $idCardTemplateConfigs->firstWhere('name', Arr::get($template, 'name'));

                return [
                    'label' => Str::toWord($template['name']),
                    'value' => $template['name'],
                    'for' => $template['for'],
                    'type' => 'custom',
                    'height' => Arr::get($templateConfig, 'height', 9.9),
                    'width' => Arr::get($templateConfig, 'width', 6.7),
                ];
            });

        $batches = Batch::getList();

        $serviceTypes = ServiceType::getOptions();

        $employeeTypes = EmployeeType::getOptions();

        $availableServices = explode(',', config('config.student.services'));

        $serviceTypes = collect($serviceTypes)->filter(function ($type) use ($availableServices) {
            return in_array(Arr::get($type, 'value'), $availableServices);
        })->values()->toArray();

        $serviceRequestTypes = ServiceRequestType::getOptions();

        return compact('templates', 'batches', 'serviceTypes', 'serviceRequestTypes', 'employeeTypes');
    }

    public function print(Request $request)
    {
        $request->validate([
            'template' => ['required', 'string'],
            'height' => ['required', 'numeric', 'min:1', 'max:20'],
            'width' => ['required', 'numeric', 'min:1', 'max:20'],
        ]);

        $templateName = Str::beforeLast($request->template, '-');

        if (! view()->exists(config('config.print.custom_path').'academic.id-card.'.$templateName) && ! view()->exists('print.academic.id-card.'.$templateName)) {
            $templateName = 'index';
        }

        $height = $request->height;
        $width = $request->width;

        $content = null;

        $predefinedTemplates = $this->getPredefinedTemplates();

        $template = collect($predefinedTemplates)->firstWhere('name', $request->template);

        if (! $template) {
            $customTemplates = $this->getCustomTemplates();

            $template = collect($customTemplates)->firstWhere('name', $request->template);
        }

        if (! $template) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('academic.id_card.template.template')])]);
        }

        if ($template['type'] == 'predefined') {
            $content = view("print.academic.id-card.{$request->template}", compact('height', 'width'))->render();
        } else {
            $content = view("print.custom.academic.id-card.templates.{$request->template}", compact('height', 'width'))->render();
        }

        $data = [];

        if (Arr::get($template, 'for') == 'student') {
            $params = [];

            if ($request->boolean('show_all_student')) {
                $params['status'] = 'all';
            }

            if ($request->students) {
                $params['students'] = Str::toArray($request->students);
            }

            $params['service'] = $request->service;
            $params['service_request_type'] = $request->service_request_type;

            $students = collect([]);
            if ($request->batch) {
                $students = (new FetchBatchWiseStudent)->execute([
                    'batch' => $request->batch,
                    'show_detail' => true,
                    ...$params,
                ]);
            } elseif ($request->groups || $request->service) {
                $students = (new FetchBatchWiseStudent)->execute([
                    'validate_batch' => false,
                    'groups' => Str::toArray($request->groups),
                    'show_detail' => true,
                    ...$params,
                ]);
            } elseif ($request->code_number) {
                $students = Student::query()
                    ->detail()
                    ->whereHas('admission', function ($q) use ($request) {
                        $q->where('code_number', $request->code_number);
                    })
                    ->get();
            }

            if ($students->isEmpty()) {
                throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('student.student')])]);
            }

            $transportRoutePassengers = RoutePassenger::query()
                ->with('route', 'stoppage')
                ->whereIn('model_id', $students->pluck('id'))
                ->where('model_type', 'Student')
                ->get();

            $today = today()->format('Y-m-d');

            $studentFees = Fee::query()
                ->join('fee_installments', 'student_fees.fee_installment_id', '=', 'fee_installments.id')
                ->select('student_fees.id', 'student_fees.student_id', 'student_fees.transport_circle_id', 'student_fees.student_id', \DB::raw('
                    COALESCE(student_fees.due_date, fee_installments.due_date) as due_date
                '))
                ->whereRaw('COALESCE(student_fees.due_date, fee_installments.due_date) <= ?', [$today])
                ->whereIn('student_fees.student_id', $students->pluck('id'))
                ->whereNotNull('fee_installments.transport_fee_id')
                ->whereNotNull('student_fees.transport_circle_id')
                ->orderBy('due_date', 'desc')
                ->get();

            $guardians = Guardian::query()
                ->with('contact')
                ->whereIn('primary_contact_id', $students->pluck('contact_id'))
                ->whereIn('relation', ['father', 'mother'])
                ->get();

            foreach ($students as $student) {
                $studentTransportFee = $studentFees->where('student_id', $student->id)->first();

                $student->transport = trans('general.no');
                if ($studentTransportFee) {
                    $student->transport = trans('general.yes');
                }

                $studenGuardians = $guardians->where('primary_contact_id', $student->contact_id);

                $transportRoutePassenger = $transportRoutePassengers->firstWhere('model_id', $student->id);

                $student->route_name = $transportRoutePassenger?->route?->name;
                $student->stoppage_name = $transportRoutePassenger?->stoppage?->name;

                // $student->barcode = (new DNS1D())
                //     ->getBarcodeHTML($student->code_number, 'C128');

                // $student->qr_code = (new QRCode())->render(
                //     $student->code_number
                // );

                $father = $studenGuardians->where('relation', 'father')->first();
                $mother = $studenGuardians->where('relation', 'mother')->first();

                $fatherIdNumber = $father?->uuid ? strtoupper(Str::before($father?->uuid, '-')) : 'NA';
                $motherIdNumber = $mother?->uuid ? strtoupper(Str::before($mother?->uuid, '-')) : 'NA';

                // $student->father_barcode = (new DNS1D())
                //     ->getBarcodeHTML($fatherIdNumber, 'C128');
                // $student->mother_barcode = (new DNS1D())
                //     ->getBarcodeHTML($motherIdNumber, 'C128');

                // $student->father_qr_code = (new QRCode())->render(
                //     $fatherIdNumber
                // );
                // $student->mother_qr_code = (new QRCode())->render(
                //     $motherIdNumber
                // );

                $student->father = [
                    'id_number' => $fatherIdNumber,
                    'contact_number' => $father?->contact?->contact_number,
                    'email' => $father?->contact?->email,
                    'photo' => $father?->contact?->photo_url,
                ];

                $student->mother = [
                    'id_number' => $motherIdNumber,
                    'contact_number' => $mother?->contact?->contact_number,
                    'email' => $mother?->contact?->email,
                    'photo' => $mother?->contact?->photo_url,
                ];

                $data[] = $this->parse($content, $student);
            }
        } elseif (Arr::get($template, 'for') == 'guardian') {
            $params = [];

            if ($request->boolean('show_all_student')) {
                $params['status'] = 'all';
            }

            if ($request->batch) {
                $students = (new FetchBatchWiseStudent)->execute([
                    'batch' => $request->batch,
                    ...$params,
                ]);
            } elseif ($request->code_number) {
                $students = Student::query()
                    ->summary()
                    ->whereHas('admission', function ($q) use ($request) {
                        $q->where('code_number', $request->code_number);
                    })
                    ->get();
            }

            $guardians = Guardian::query()
                ->with('contact')
                ->whereIn('primary_contact_id', $students->pluck('contact_id'))
                ->get();

            $studentContactIds = Guardian::query()
                ->whereIn('contact_id', $guardians->pluck('contact_id'))
                ->get()
                ->pluck('primary_contact_id')
                ->all();

            $allGuardians = Guardian::query()
                ->whereIn('primary_contact_id', $studentContactIds)
                ->get();

            $students = Student::query()
                ->summary()
                ->whereIn('contact_id', $studentContactIds)
                ->get();

            foreach ($guardians as $guardian) {
                $allGuardian = $allGuardians->where('contact_id', $guardian->contact_id);

                $relatedStudents = $students->filter(function ($student) use ($allGuardian) {
                    return in_array($student->contact_id, $allGuardian->pluck('primary_contact_id')->all());
                });

                $guardian->related_students = $relatedStudents;

                $data[] = $this->parse($content, $guardian);
            }
        } elseif (Arr::get($template, 'for') == 'employee') {
            $employees = collect([]);

            $request->merge([
                'paginate' => false,
                'types' => Str::toArray($request->employee_types),
            ]);

            if ($request->department) {
                $employees = (new FetchEmployee)->execute($request);
            } else {
                $employees = Employee::query()
                    ->byTeam()
                    ->where('code_number', $request->code_number)
                    ->get();
            }

            if ($employees->isEmpty()) {
                throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('employee.employee')])]);
            }

            foreach ($employees as $employee) {

                // $employee->qr_code = (new QRCode())->render(
                //     $employee->code_number
                // );

                $data[] = $this->parse($content, $employee);
            }
        } else {
            // $guardians = Guardian::query()
            //     ->get();

            // foreach ($students as $student) {
            //     $data[] = $this->parse($content, $student);
            // }
        }

        $column = $request->query('column') ?? 1;
        $cardPerPage = $request->query('card_per_page') ?? 1;

        return view()->first([config('config.print.custom_path').'academic.id-card.'.$templateName, 'print.academic.id-card.'.$templateName], compact('data', 'column', 'cardPerPage', 'height', 'width'))->render();
    }
}
