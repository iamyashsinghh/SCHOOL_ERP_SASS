<?php

namespace App\Actions\Task;

use App\Models\Tenant\Task\Task;
use Illuminate\Http\Request;

class UpdateTask
{
    public function execute(Request $request, Task $task): Task
    {
        \DB::beginTransaction();

        $params = (new FormatTaskParam)->execute($request);

        $task->forceFill($params)->save();

        $task->updateMedia($request);

        $task->setMeta([
            'custom_fields' => $request->custom_fields ?? [],
        ]);
        $task->save();

        \DB::commit();

        return $task;
    }
}
