<?php

namespace App\Actions\Task;

use App\Models\Tenant\Employee\Employee;
use App\Models\Tenant\Task\Member;
use App\Models\Tenant\Task\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateTask
{
    public function execute(Request $request): Task
    {
        \DB::beginTransaction();

        $params = (new FormatTaskParam)->execute($request);

        $codeNumberDetail = (new GenerateCodeNumber)->execute();

        $params['number_format'] = Arr::get($codeNumberDetail, 'number_format');
        $params['number'] = Arr::get($codeNumberDetail, 'number');
        $params['code_number'] = Arr::get($codeNumberDetail, 'code_number');

        $params['team_id'] = auth()->user()?->current_team_id;
        $params['user_id'] = auth()->id();

        $task = Task::forceCreate($params);
        $task->setMeta([
            'custom_fields' => $request->custom_fields ?? [],
        ]);

        $task->addMedia($request);

        $this->addOwner($task);

        \DB::commit();

        return $task;
    }

    private function addOwner(Task $task): void
    {
        $employee = Employee::auth()->first();

        // if (! $employee) {
        //     return;
        // throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('employee.employee')])]);
        // }

        Member::forceCreate([
            'task_id' => $task->id,
            'employee_id' => $employee?->id,
            'is_owner' => 1,
        ]);
    }
}
