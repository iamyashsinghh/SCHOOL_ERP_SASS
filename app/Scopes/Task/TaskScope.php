<?php

namespace App\Scopes\Task;

use App\Models\Task\Member;
use Illuminate\Database\Eloquent\Builder;

trait TaskScope
{
    public function scopeWithOwner(Builder $query)
    {
        $query->addSelect(['owner_id' => Member::select('employee_id')
            ->whereColumn('task_id', 'tasks.id')
            ->where('is_owner', 1)
            ->limit(1),
        ])->with(['owner' => fn ($q) => $q->summary()]);
    }

    public function scopeWithMember(Builder $query)
    {
        $query->leftJoin('task_members', function ($join) {
            $join->on('tasks.id', '=', 'task_members.task_id');
        })->leftJoin('employees', function ($join) {
            $join->on('task_members.employee_id', '=', 'employees.id');
        })->leftJoin('contacts', function ($join) {
            $join->on('employees.contact_id', '=', 'contacts.id')
                ->where('contacts.user_id', '=', auth()->id());
        });
    }
}
