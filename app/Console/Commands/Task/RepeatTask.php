<?php

namespace App\Console\Commands\Task;

use App\Actions\Task\ReplicateChecklist;
use App\Actions\Task\ReplicateMedia;
use App\Actions\Task\ReplicateMember;
use App\Actions\Task\ReplicateTask;
use App\Actions\UpdateNextRepeatDate;
use App\Models\Config\Config;
use App\Models\Task\Task;
use App\Models\Team;
use App\Support\SetConfig;
use Illuminate\Console\Command;

class RepeatTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:repeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repeat task';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        foreach (Team::get(['id']) as $team) {

            $config = Config::listAll($team->id);
            (new SetConfig)->set($config);

            // Repeated tasks are generated one day prior to the start date only when the task is not cancelled

            $toRepeatTasks = Task::query()
                ->with('members', 'checklists')
                ->where('repeatation->next_repeat_date', '=', today()->addDay(1)->toDateString())
                ->whereNull('cancelled_at')
                ->get();

            $uuids = [];
            foreach ($toRepeatTasks as $task) {
                if (! Task::query()
                    ->whereTitle($task->title)
                    ->where('start_date', '=', $task->repeatation['next_repeat_date'])
                    ->where('meta->repeated_task_uuid', $task->uuid)
                    ->exists()
                ) {
                    $uuids[] = $task->uuid;
                }
            }

            $tasks = $toRepeatTasks->whereIn('uuid', $uuids);

            if (! $tasks->count()) {
                $this->error('There are no tasks to repeat.');
                exit;
            }

            foreach ($tasks as $task) {
                $newTask = (new ReplicateTask)->execute($task);

                (new ReplicateMember)->execute($newTask, $task->members);

                (new ReplicateMedia)->execute($newTask, $task->media);

                (new ReplicateChecklist)->execute($newTask, $task->checklists, $task->start_date->value);

                (new UpdateNextRepeatDate)->execute($task);

                $this->info("Task {$task->code_number} has been repeated.");
            }
        }
    }
}
