<?php

namespace App\Actions\Task;

use App\Models\Task\Task;
use App\Support\FormatCodeNumber;

class GenerateCodeNumber
{
    use FormatCodeNumber;

    public function execute(?Task $task = null): array
    {
        $numberPrefix = config('config.task.code_number_prefix');
        $numberSuffix = config('config.task.code_number_suffix');
        $digit = config('config.task.code_number_digit', 0);

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $codeNumber = (int) Task::query()
            ->when($task, function ($q, $task) {
                $q->where('team_id', $task->team_id);
            }, function ($q) {
                $q->byTeam();
            })
            ->whereNumberFormat($numberFormat)->max('number') + 1;

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }
}
