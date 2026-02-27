<?php

namespace App\Policies\Task;

use App\Concerns\SubordinateAccess;
use App\Models\Task\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization, SubordinateAccess;

    private function hasSubordinate(Task $task)
    {
        $accessibleEmployeeIds = $this->getAccessibleEmployeeIds();
        $subordinatesAsMember = array_intersect($task->memberLists()->pluck('employee_id')->all(), $accessibleEmployeeIds);

        return count($subordinatesAsMember) ? true : false;
    }

    private function hasSubordinateOwner(Task $task)
    {
        $accessibleEmployeeIds = $this->getAccessibleEmployeeIds();

        return in_array($task->memberLists()->firstWhere('is_owner', 1)?->employee_id, $accessibleEmployeeIds) ? true : false;
    }

    /**
     * Determine whether the user can fetch prerequisites any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function preRequisite(User $user)
    {
        return $user->can('task:read');
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return $user->can('task:read');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Task $task)
    {
        if (! $user->can('task:read')) {
            return false;
        }

        if ($task->is_owner) {
            return true;
        }

        if ($task->member_user_id == $user->id) {
            return true;
        }

        if (! config('config.task.is_accessible_to_top_level')) {
            return false;
        }

        return $this->hasSubordinate($task);
    }

    /**
     * Determine whether the user can create models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->can('task:create');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Task $task)
    {
        if (! $user->can('task:edit')) {
            return false;
        }

        if ($task->is_owner) {
            return true;
        }

        if (! config('config.task.is_manageable_by_top_level')) {
            return false;
        }

        return $this->hasSubordinateOwner($task);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Task $task)
    {
        if (! $user->can('task:delete')) {
            return false;
        }

        if ($task->is_owner) {
            return true;
        }

        if (! config('config.task.is_manageable_by_top_level')) {
            return false;
        }

        return $this->hasSubordinateOwner($task);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Task $task)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Task $task)
    {
        //
    }
}
