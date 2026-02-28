<?php

namespace App\Policies\Helpdesk\Faq;

use App\Models\Tenant\Helpdesk\Faq\Faq;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FaqPolicy
{
    use HandlesAuthorization;

    private function validateTeam(User $user, Faq $faq)
    {
        return $faq->team_id == $user->current_team_id;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function config(User $user)
    {
        return $user->can('helpdesk:config');
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return $user->can('faq:read');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Faq $faq)
    {
        if (! $this->validateTeam($user, $faq)) {
            return false;
        }

        return $user->can('faq:read');
    }

    /**
     * Determine whether the user can create models.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->can('faq:create');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Faq $faq)
    {
        if (! $this->validateTeam($user, $faq)) {
            return false;
        }

        return $user->can('faq:edit');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Faq $faq)
    {
        if (! $this->validateTeam($user, $faq)) {
            return false;
        }

        return $user->can('faq:delete');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Faq $faq)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Faq $faq)
    {
        //
    }
}
