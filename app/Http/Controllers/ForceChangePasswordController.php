<?php

namespace App\Http\Controllers;

use App\Rules\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ForceChangePasswordController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = auth()->user();

        if (empty($user)) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        if (! $user->getMeta('force_change_password')) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        if (! $user->password) {
            throw ValidationException::withMessages(['current_password' => __('general.errors.invalid_action')]);
        }

        $request->validate([
            'new_password' => ['required', 'same:new_password_confirmation', 'different:current_password', new StrongPassword],
        ]);

        if (\Hash::check($request->new_password, $user->password)) {
            throw ValidationException::withMessages(['new_password' => __('auth.password.errors.different')]);
        }

        $user = $user;
        $user->password = bcrypt($request->new_password);
        $user->setMeta(['force_change_password' => false, 'last_force_password_change_at' => now()->toDateTimeString()]);
        $user->save();

        return response()->success([
            'message' => trans('auth.password.force_password_changed'),
        ]);
    }
}
