<?php

namespace App\Actions\Task;

use App\Models\Task\Task;
use Illuminate\Support\Collection;

class ReplicateMedia
{
    public function execute(Task $task, Collection $checklists): void {}
}
