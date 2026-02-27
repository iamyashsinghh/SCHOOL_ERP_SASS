<?php

namespace App\Actions\Task;

use App\Models\Task\Member;
use App\Models\Task\Task;
use Illuminate\Support\Collection;

class ReplicateMember
{
    public function execute(Task $task, Collection $members): void
    {
        foreach ($members as $member) {
            Member::forceCreate([
                'task_id' => $task->id,
                'employee_id' => $member->employee_id,
                'is_owner' => $member->is_owner,
                'meta' => $member->meta,
            ]);
        }
    }
}
