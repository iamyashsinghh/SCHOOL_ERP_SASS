<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Scopes\SassSchoolScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        // Attempt to find user globally without the school scope
        $user = User::withoutGlobalScope(SassSchoolScope::class)
            ->where('email', $request->email)
            ->orWhere('username', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Check if user has a school assigned (they should if they belong to a school)
        $schoolId = $user->sass_school_id ?? null;

        // Create token with school context embedded in abilities
        $abilities = ['mobile-access'];
        if ($schoolId) {
            $abilities[] = "sass_school_id:{$schoolId}";
        }

        $token = $user->createToken('mobile-app', $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'school_id' => $schoolId,
                'role' => $user->user_role,
            ],
            'permissions' => $user->user_permission,
            // You can add modules array based on permissions if needed
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'user' => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $user->user_role,
            ],
            'permissions' => $user->user_permission,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
