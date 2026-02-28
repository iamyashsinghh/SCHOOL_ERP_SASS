<?php

namespace App\Imports\Employee;

use App\Concerns\ItemImport;
use App\Enums\OptionType;
use App\Helpers\CalHelper;
use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Experience;
use App\Models\Tenant\Option;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExperienceImport implements ToCollection, WithHeadingRow
{
    use ItemImport;

    protected $limit = 1000;

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('employee_experience');

        [$errors, $rows] = $this->validate($rows);

        $this->checkForErrors('employee_experience', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $employees = Employee::query()
            ->byTeam()
            ->whereIn('id', $rows->pluck('employee_id'))
            ->get();

        activity()->disableLogging();

        \DB::beginTransaction();

        foreach ($rows as $row) {
            $employmentTypeId = Arr::get($row, 'employment_type_id');
            $headline = Arr::get($row, 'headline');
            $title = Arr::get($row, 'title');
            $organizationName = Arr::get($row, 'organization_name');
            $location = Arr::get($row, 'location');
            $startDate = Arr::get($row, 'start_date');
            $endDate = Arr::get($row, 'end_date');
            $jobProfile = Arr::get($row, 'job_profile');
            $submittedOriginal = (bool) Arr::get($row, 'submitted_original');

            $employee = $employees->firstWhere('id', Arr::get($row, 'employee_id'));

            if ($startDate) {
                if (is_int($startDate)) {
                    $startDate = Date::excelToDateTimeObject($startDate)->format('Y-m-d');
                } else {
                    $startDate = Carbon::parse($startDate)->toDateString();
                }
            }

            if ($endDate) {
                if (is_int($endDate)) {
                    $endDate = Date::excelToDateTimeObject($endDate)->format('Y-m-d');
                } else {
                    $endDate = Carbon::parse($endDate)->toDateString();
                }
            }

            $experience = Experience::query()
                ->where('model_type', 'Contact')
                ->where('model_id', $employee->contact_id)
                ->where('organization_name', $organizationName)
                ->where('title', $title)
                ->first();

            if ($experience) {
                $experience->update([
                    'employment_type_id' => $employmentTypeId,
                    'headline' => $headline,
                    'location' => $location,
                    'job_profile' => $jobProfile,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);
                $experience->setMeta([
                    'is_submitted_original' => $submittedOriginal,
                ]);
                $experience->save();
            } else {
                $experience = Experience::forceCreate([
                    'model_type' => 'Contact',
                    'model_id' => $employee->contact_id,
                    'organization_name' => $organizationName,
                    'title' => $title,
                    'employment_type_id' => $employmentTypeId,
                    'headline' => $headline,
                    'location' => $location,
                    'job_profile' => $jobProfile,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'meta' => [
                        'media_token' => (string) Str::uuid(),
                        'is_submitted_original' => $submittedOriginal,
                    ],
                ]);
            }
        }

        \DB::commit();

        activity()->enableLogging();
    }

    private function validate(Collection $rows)
    {
        $errors = [];

        $employmentTypes = Option::query()
            ->byTeam()
            ->where('type', OptionType::EMPLOYMENT_TYPE)
            ->get();

        $employees = Employee::query()
            ->summary()
            ->get();

        $newRows = [];
        $newRecords = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $name = Arr::get($row, 'employee');
            $employmentType = Arr::get($row, 'employment_type');
            $headline = Arr::get($row, 'headline');
            $title = Arr::get($row, 'title');
            $organizationName = Arr::get($row, 'organization_name');
            $location = Arr::get($row, 'location');
            $startDate = Arr::get($row, 'start_date');
            $endDate = Arr::get($row, 'end_date');
            $jobProfile = Arr::get($row, 'job_profile');

            if (! $name) {
                $errors[] = $this->setError($rowNo, trans('employee.props.name'), 'required');
            } elseif (! $employees->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first()) {
                $errors[] = $this->setError($rowNo, trans('employee.props.name'), 'invalid');
            }

            if (! $employmentType) {
                $errors[] = $this->setError($rowNo, trans('employee.employment_type.employment_type'), 'required');
            }

            if ($employmentType) {
                $employmentType = $employmentTypes->filter(function ($item) use ($employmentType) {
                    return strtolower($item->name) == strtolower($employmentType);
                })->first();

                if (! $employmentType) {
                    $errors[] = $this->setError($rowNo, trans('employee.employment_type.employment_type'), 'invalid');
                }
            }

            if ($headline && strlen($headline) > 100) {
                $errors[] = $this->setError($rowNo, trans('employee.experience.props.headline'), 'max', ['max' => 100]);
            }

            if (! $title) {
                $errors[] = $this->setError($rowNo, trans('employee.experience.props.title'), 'required');
            } elseif (strlen($title) > 100) {
                $errors[] = $this->setError($rowNo, trans('employee.experience.props.title'), 'max', ['max' => 100]);
            }

            if (! $organizationName) {
                $errors[] = $this->setError($rowNo, trans('employee.experience.props.organization_name'), 'required');
            } elseif (strlen($organizationName) > 100) {
                $errors[] = $this->setError($rowNo, trans('employee.experience.props.organization_name'), 'max', ['max' => 100]);
            }

            if ($location && strlen($location) > 100) {
                $errors[] = $this->setError($rowNo, trans('employee.experience.props.location'), 'max', ['max' => 100]);
            }

            if ($startDate && is_int($startDate)) {
                $startDate = Date::excelToDateTimeObject($startDate)->format('Y-m-d');
            }

            if ($startDate && ! CalHelper::validateDate($startDate)) {
                $errors[] = $this->setError($rowNo, trans('employee.experience.props.start_date'), 'invalid');
            }

            if ($endDate && is_int($endDate)) {
                $endDate = Date::excelToDateTimeObject($endDate)->format('Y-m-d');
            }

            if ($endDate && ! CalHelper::validateDate($endDate)) {
                $errors[] = $this->setError($rowNo, trans('employee.experience.props.end_date'), 'invalid');
            }

            if ($startDate && $endDate && $startDate > $endDate) {
                $errors[] = $this->setError($rowNo, trans('employee.experience.props.start_date'), 'date_before', ['date' => trans('employee.experience.props.end_date')]);
            }

            if ($jobProfile && strlen($jobProfile) > 100) {
                $errors[] = $this->setError($rowNo, trans('employee.experience.props.job_profile'), 'max', ['max' => 100]);
            }

            $employee = $employees->filter(function ($item) use ($name) {
                return strtolower($item->name) == strtolower($name) || $item->code_number == $name;
            })->first();

            $row['employee_id'] = $employee?->id;
            $row['contact_id'] = $employee?->contact_id;
            $row['employment_type_id'] = $employmentType?->id;
            $newRows[] = $row;
        }

        $rows = collect($newRows);

        return [$errors, $rows];
    }
}
