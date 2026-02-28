<?php

namespace App\Policies\Finance;

use App\Models\Tenant\Finance\Receipt;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReceiptPolicy
{
    use HandlesAuthorization;

    private function validateTeam(User $user, Receipt $receipt)
    {
        return $receipt->period->team_id == $user->current_team_id;
    }

    /**
     * Determine whether the user can request for pre-requisites.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function preRequisite(User $user)
    {
        return $user->canAny(['receipt:create', 'receipt:edit']);
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return $user->can('receipt:read');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Receipt $receipt)
    {
        if (! $this->validateTeam($user, $receipt)) {
            return false;
        }

        return $user->can('receipt:read');
    }

    /**
     * Determine whether the user can create models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->can('receipt:create');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Receipt $receipt)
    {
        if (! $this->validateTeam($user, $receipt)) {
            return false;
        }

        return $user->can('receipt:edit');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Receipt $receipt)
    {
        if (! $this->validateTeam($user, $receipt)) {
            return false;
        }

        return $user->can('receipt:delete');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Receipt $receipt)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Receipt $receipt)
    {
        //
    }
}
