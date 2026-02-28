<?php

namespace App\Listeners;

use App\Events\Auth\UserLogin;

class UserLoginListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(UserLogin $event)
    {
        //
    }
}
