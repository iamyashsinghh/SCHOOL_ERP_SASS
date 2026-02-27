<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SetPushToken;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Get logged in user
     */
    public function me(Request $request)
    {
        $user = \Auth::user();

        if ($user) {
            $user->validateStatus();

            $user->validateIp($request->ip());
        }

        (new SetPushToken)->execute($user);

        return AuthUserResource::make($user);
    }

    public function confirmPassword(Request $request)
    {
        $user = \Auth::user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['message' => trans('general.alerts.password_error')]);
        }
    }
}
