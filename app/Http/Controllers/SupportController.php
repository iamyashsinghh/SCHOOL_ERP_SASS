<?php

namespace App\Http\Controllers;

use App\Helpers\SysHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! auth()->user()->is_default) {
            throw ValidationException::withMessages([
                'message' => trans('user.errors.permission_denied'),
            ]);
        }

        $supportToken = Str::random(32);

        SysHelper::setApp([
            'SUPPORT_TOKEN' => $supportToken,
            'SUPPORT_TOKEN_EXPIRY' => now()->addMinutes(10),
        ]);

        return response()->json([
            'message' => 'Support token generated successfully. You can share it with support team.',
            'token' => $supportToken,
        ]);
    }
}
