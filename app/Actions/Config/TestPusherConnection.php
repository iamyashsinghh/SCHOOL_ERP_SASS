<?php

namespace App\Actions\Config;

use App\Events\TestEvent;
use App\Models\Tenant\User;
use Illuminate\Http\Request;

class TestPusherConnection
{
    public function execute(Request $request)
    {
        $user = User::first();

        broadcast(new TestEvent($user));
    }
}
