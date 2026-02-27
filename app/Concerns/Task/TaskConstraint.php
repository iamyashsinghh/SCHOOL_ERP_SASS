<?php

namespace App\Concerns\Task;

use Illuminate\Validation\ValidationException;

trait TaskConstraint
{
    public function ensureCanManage($permission): void
    {
        if (! $this->canManage($permission)) {
            if ($permission == 'task_list') {
                throw ValidationException::withMessages(['message' => trans('task.list.could_not_perform_if_not_permitted')]);
            }

            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }
    }

    public function ensureIsOwner(): void
    {
        if (! $this->is_owner) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }
    }

    public function ensureIsMember(): void
    {
        if (! $this->is_member) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }
    }

    public function ensureIsNotCompleted(): void
    {
        if ($this->is_completed) {
            throw ValidationException::withMessages(['message' => trans('task.could_not_perform_if_completed')]);
        }
    }

    public function ensureIsCompleted(): void
    {
        if (! $this->is_completed) {
            throw ValidationException::withMessages(['message' => trans('task.could_not_perform_if_completed')]);
        }
    }

    public function ensureIsNotCancelled(): void
    {
        if ($this->cancelled_at) {
            throw ValidationException::withMessages(['message' => trans('task.could_not_perform_if_cancelled')]);
        }
    }

    public function ensureIsActionable(): void
    {
        if (! $this->isActionable()) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }
    }

    public function ensureIsEditable(): void
    {
        if (! $this->isEditable()) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }
    }

    public function ensureIsDeletable(): void
    {
        if (! $this->isDeletable()) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }
    }
}
