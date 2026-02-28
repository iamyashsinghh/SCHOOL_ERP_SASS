<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class SsoController extends Controller
{
    public function login(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            abort(403, 'Invalid SSO token.');
        }

        $ssoData = Cache::get("sso_token_{$token}");

        if (!$ssoData) {
            abort(403, 'SSO token expired or invalid.');
        }

        // Token is valid, forget it to prevent reuse
        Cache::forget("sso_token_{$token}");

        // Now we are inherently on the tenant subdomain due to routing,
        // and IdentifyTenant middleware has already set up the tenant DB connection.
        
        $adminUsername = $ssoData['admin_username'];
        $user = User::where('username', $adminUsername)->first();

        // Fallback: If username doesn't strictly match, try getting the first user with admin role
        if (!$user) {
            $user = User::role('admin')->first();
        }

        if (!$user) {
            abort(404, 'Admin user not found in this tenant.');
        }

        // Perform login
        Auth::guard('web')->login($user);

        // Redirect to dashboard
        return redirect('/app/dashboard');
    }
}
