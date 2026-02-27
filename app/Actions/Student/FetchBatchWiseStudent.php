<?php

namespace App\Actions\Student;

use App\Contracts\PaginationHelper;
use App\Models\Student\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FetchBatchWiseStudent extends PaginationHelper
{
    public function execute(array $params = [], bool $array = false)
    {
        $validateBatch = (bool) Arr::get($params, 'validate_batch', true);

        if ($validateBatch) {
            Validator::make($params, [
                'batch' => 'required',
                'students' => 'array',
            ], [], [
                'batch' => trans('academic.batch.batch'),
                'students' => trans('student.student'),
            ])->validate();
        }

        $onDate = Arr::get($params, 'on_date') ?? today()->toDateString();

        $selectAll = Arr::get($params, 'select_all') == true ? true : false;

        $paginate = false;
        if (array_key_exists('paginate', $params) && Arr::get($params, 'paginate') == true) {
            $paginate = true;
        }

        $uuids = [];
        if (count(Arr::get($params, 'students', []))) {
            foreach (Arr::get($params, 'students', []) as $student) {
                $uuids[] = is_array($student) ? Arr::get($student, 'uuid') : $student;
            }
        }

        $name = Arr::get($params, 'name');
        $batch = Arr::get($params, 'batch');
        $status = Arr::get($params, 'status', 'studying');
        $forSubject = (bool) Arr::get($params, 'for_subject');
        $groups = Str::toArray(Arr::get($params, 'groups', []));
        $service = Arr::get($params, 'service');
        $serviceRequestType = Arr::get($params, 'service_request_type');

        if ($selectAll) {
            $uuids = [];
        }

        $feeStructureType = Arr::get($params, 'fee_structure_type', 'all');
        $feeStructureUuid = Arr::get($params, 'fee_structure_uuid');

        $withFeeStructure = Arr::get($params, 'with_fee_structure', false);

        $query = Student::query()
            ->when(Arr::get($params, 'show_detail'), function ($q) {
                $q->detail();
            }, function ($q) {
                $q->summary();
            })
            ->byPeriod()
            ->filterByStatus($status)
            ->filterAccessible($forSubject)
            ->when($withFeeStructure, function ($q) {
                $q->with('feeStructure');
            })
            ->when(Arr::get($params, 'fees_count'), function ($q) {
                $q->withCount(['fees' => function ($q) {
                    $q->where('total', '>', 0);
                }]);
            })
            ->when($feeStructureType == 'without', function ($q) {
                $q->whereNull('fee_structure_id');
            })
            ->when($feeStructureType == 'with', function ($q) {
                $q->whereNotNull('fee_structure_id');
            })
            ->when($feeStructureUuid, function ($q, $feeStructureUuid) {
                $q->whereHas('feeStructure', function ($q) use ($feeStructureUuid) {
                    $q->where('uuid', $feeStructureUuid);
                });
            })
            ->when(Arr::get($params, 'with_fee_concession_type'), function ($q) {
                $q->with('feeConcessionType');
            })
            // ->where(function ($q) use ($onDate) {
            //     $q->whereNull('admissions.leaving_date')
            //         ->orWhere('admissions.leaving_date', '>', $onDate);
            // })
            ->when($name, function ($q, $name) {
                $q->where(\DB::raw('REGEXP_REPLACE(CONCAT_WS(" ", first_name, middle_name, third_name, last_name), "[[:space:]]+", " ")'), 'like', "%{$name}%");
            })
            ->when($batch, function ($q, $batch) {
                if (is_array($batch)) {
                    $q->whereIn('batches.uuid', $batch);
                } else {
                    $q->where('batches.uuid', $batch);
                }
            })
            ->when($groups, function ($q, $groups) {
                $q->leftJoin('group_members', 'students.id', 'group_members.model_id')
                    ->where('group_members.model_type', 'Student')
                    ->join('options as student_groups', 'group_members.model_group_id', 'student_groups.id')
                    ->whereIn('student_groups.uuid', $groups);
            })
            ->when($service, function ($q, $service) use ($serviceRequestType) {
                $q->leftJoin('service_allocations', function ($join) use ($service) {
                    $join->on('students.id', '=', 'service_allocations.model_id')
                        ->where('service_allocations.model_type', 'Student')
                        ->where('service_allocations.type', $service);
                })
                    ->when($serviceRequestType == 'opt_in', function ($q) {
                        $q->whereNotNull('service_allocations.id');
                    })
                    ->when($serviceRequestType == 'opt_out', function ($q) {
                        $q->whereNull('service_allocations.id');
                    });
            })
            ->when($uuids, function ($q, $uuids) {
                $q->whereIn('students.uuid', $uuids);
            });

        $sortBy = Arr::get($params, 'sort', 'name');
        $orderBy = Arr::get($params, 'order', 'asc');

        if (! $paginate) {
            $students = $query
                ->when($sortBy == 'admission_date', function ($q) use ($orderBy) {
                    $q->orderBy('joining_date', $orderBy);
                })
                ->when($sortBy == 'code_number', function ($q) use ($orderBy) {
                    $q->orderBy('code_number', $orderBy);
                })
                ->when($sortBy == 'name', function ($q) use ($orderBy) {
                    $q->orderBy('name', $orderBy);
                })
                ->orderBy('number', $orderBy)
                ->orderBy('students.id', $orderBy)
                ->get();
        } else {
            $perPage = Arr::get($params, 'per_page', $this->getPageLength());

            $students = $query
                ->when($sortBy == 'admission_date', function ($q) use ($orderBy) {
                    $q->orderBy('joining_date', $orderBy);
                })
                ->when($sortBy == 'code_number', function ($q) use ($orderBy) {
                    $q->orderBy('code_number', $orderBy);
                })
                ->when($sortBy == 'name', function ($q) use ($orderBy) {
                    $q->orderBy('name', $orderBy);
                })
                ->orderBy('number', $orderBy)
                ->orderBy('students.id', $orderBy)
                ->paginate($perPage, ['*'], 'current_page');
        }

        return $array ? $students->toArray() : $students;
    }
}
