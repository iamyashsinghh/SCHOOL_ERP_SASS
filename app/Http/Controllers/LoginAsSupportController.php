<?php

namespace App\Http\Controllers;

use App\Helpers\SysHelper;
use App\Models\Tenant\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LoginAsSupportController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        if (auth()->check()) {
            abort(404);
        }

        if (! config('config.system.enable_author_support')) {
            abort(404);
        }

        if ($token != SysHelper::getApp('SUPPORT_TOKEN')) {
            abort(404);
        }

        $expiry = SysHelper::getApp('SUPPORT_TOKEN_EXPIRY');

        try {
            if ($expiry && Carbon::parse($expiry)->isPast()) {
                abort(404);
            }
        } catch (\Exception $e) {
            abort(404);
        }

        SysHelper::setApp([
            'SUPPORT_TOKEN' => '',
            'SUPPORT_TOKEN_EXPIRY' => '',
        ]);

        $user = User::query()
            ->where('meta->is_default', true)
            ->firstOrFail();

        \Auth::login($user);

        return redirect('/app');
    }
}
