<?php

namespace App\Services\Student;

use App\Actions\Student\FetchBatchWiseStudent;
use App\Http\Resources\Student\StudentResource;
use App\Models\Academic\Batch;
use App\Models\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class RollNumberService
{
    public function preRequisite(Request $request)
    {
        $batches = Batch::getList();

        $sortBy = [
            ['label' => trans('student.props.name'), 'value' => 'name'],
            ['label' => trans('student.roll_number.roll_number'), 'value' => 'roll_number'],
            ['label' => trans('student.admission.props.date'), 'value' => 'admission_date'],
            ['label' => trans('student.admission.props.code_number'), 'value' => 'code_number'],
        ];

        $orderBy = [
            ['label' => trans('list.orders.asc'), 'value' => 'asc'],
            ['label' => trans('list.orders.desc'), 'value' => 'desc'],
        ];

        return compact('batches', 'sortBy', 'orderBy');
    }

    private function validateInput(Request $request): Batch
    {
        return Batch::query()
            ->byPeriod()
            ->filterAccessible()
            ->whereUuid($request->batch)
            ->getOrFail(trans('academic.batch.batch'), 'batch');
    }

    public function fetch(Request $request)
    {
        $batch = $this->validateInput($request);

        $params = $request->all();
        $params['select_all'] = true;

        if ($request->boolean('show_all_student')) {
            $params['status'] = 'all';
        }

        if (in_array($request->query('sort'), ['name', 'admission_date', 'roll_number', 'code_number'])) {
            $params['sort'] = $request->query('sort');
        } else {
            $params['sort'] = 'name';
        }

        $students = (new FetchBatchWiseStudent)->execute($params);

        return StudentResource::collection($students)
            ->additional([
                'meta' => [
                    'roll_number_prefix' => Arr::get($batch->config, 'roll_number_prefix'),
                ],
            ]);
    }

    public function store(Request $request)
    {
        $batch = $this->validateInput($request);

        $request->merge(['select_all' => true]);

        $students = (new FetchBatchWiseStudent)->execute($request->all(), true);

        if (array_diff(Arr::pluck($request->students, 'uuid'), Arr::pluck($students, 'uuid'))) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        foreach ($request->students as $index => $input) {
            $number = Arr::get($input, 'number') ?: null;

            $student = Student::where('uuid', Arr::get($input, 'uuid'))->first();
            $student->number = $number;
            $student->roll_number = $number ? (Arr::get($batch->config, 'roll_number_prefix').$number) : null;
            $student->save();
        }
    }
}
