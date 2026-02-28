<?php

namespace App\Policies\Helpdesk\Ticket;

use App\Concerns\SubordinateAccess;
use App\Enums\Helpdesk\Ticket\Status as TicketStatus;
use App\Models\Tenant\Helpdesk\Ticket\Ticket;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization, SubordinateAccess;

    private function validateTeam(User $user, Ticket $ticket)
    {
        return $ticket->team_id == $user->current_team_id;
    }

    private function hasSubordinate(Ticket $ticket)
    {
        // $accessibleEmployeeIds = $this->getAccessibleEmployeeIds();
        // $subordinatesAsMember = array_intersect($ticket->memberLists()->pluck('employee_id')->all(), $accessibleEmployeeIds);

        // return count($subordinatesAsMember) ? true : false;
        return true;
    }

    private function hasSubordinateOwner(Ticket $ticket)
    {
        // $accessibleEmployeeIds = $this->getAccessibleEmployeeIds();

        // return in_array($ticket->memberLists()->firstWhere('is_owner', 1)?->employee_id, $accessibleEmployeeIds) ? true : false;

        return true;
    }

    /**
     * Determine whether the user can fetch prerequisites any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function preRequisite(User $user)
    {
        return $user->can('ticket:read');
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return $user->can('ticket:read');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Ticket $ticket)
    {
        if (! $user->can('ticket:read')) {
            return false;
        }

        if ($ticket->user_id == auth()->id()) {
            return true;
        }

        // return $this->hasSubordinate($ticket);

        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->can('ticket:create');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Ticket $ticket)
    {
        if (! $user->can('ticket:edit')) {
            return false;
        }

        if ($ticket->is_owner) {
            return true;
        }

        if ($ticket->status != TicketStatus::OPEN->value) {
            return false;
        }

        // if (! config('config.ticket.is_manageable_by_top_level')) {
        //     return false;
        // }

        // return $this->hasSubordinateOwner($ticket);

        return true;
    }

    /**
     * Determine whether the user can take action on the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function action(User $user, Ticket $ticket)
    {
        if (! $this->validateTeam($user, $ticket)) {
            return false;
        }

        if (! $user->can('ticket:action')) {
            return false;
        }

        if ($ticket->status == TicketStatus::CLOSED->value) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can take action on the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function message(User $user, Ticket $ticket)
    {
        if (! $this->validateTeam($user, $ticket)) {
            return false;
        }

        if ($ticket->is_requester) {
            return true;
        }

        if (! $user->can('ticket:action')) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can bulk update the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function bulkUpdate(User $user)
    {
        return $user->can('ticket:edit');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Ticket $ticket)
    {
        if (! $user->can('ticket:delete')) {
            return false;
        }

        if ($ticket->status != TicketStatus::OPEN->value) {
            return false;
        }

        if ($ticket->is_owner) {
            return true;
        }

        // if (! config('config.ticket.is_manageable_by_top_level')) {
        //     return false;
        // }

        // return $this->hasSubordinateOwner($ticket);

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Ticket $ticket)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Ticket $ticket)
    {
        //
    }
}
