<?php

namespace App\Observers;

use App\Models\Tenant\Contact;
use App\Models\Tenant\User;

class ContactObserver
{
    /**
     * Handle the Contact "created" event.
     *
     * @return void
     */
    public function created(Contact $contact)
    {
        //
    }

    /**
     * Handle the Contact "updated" event.
     *
     * @return void
     */
    public function updated(Contact $contact)
    {
        if (! $contact->user_id) {
            return;
        }

        User::query()
            ->where('id', $contact->user_id)
            ->update([
                'name' => $contact->name,
            ]);
    }

    /**
     * Handle the Contact "deleted" event.
     *
     * @return void
     */
    public function deleted(Contact $contact) {}

    /**
     * Handle the Contact "restored" event.
     *
     * @return void
     */
    public function restored(Contact $contact)
    {
        //
    }

    /**
     * Handle the Contact "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Contact $contact)
    {
        //
    }
}
