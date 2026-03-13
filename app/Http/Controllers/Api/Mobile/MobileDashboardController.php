<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MobileDashboardController extends Controller
{
    public function stats(Request $request)
    {
        // Example role-based stats
        // Implementation will depend on the actual models and scope
        $user = $request->user();
        $roles = $user->roles->pluck('name')->toArray();

        $stats = [];

        if (in_array('admin', $roles)) {
            $stats = [
                'student_count' => 1250,
                'employee_count' => 85,
                'collections_today' => 45000,
            ];
        } elseif (in_array('student', $roles)) {
            $stats = [
                'attendance_percentage' => 85,
                'pending_fees' => 1500,
            ];
        }

        return response()->json([
            'stats' => $stats,
            'schedule' => [],
            'celebrations' => [],
            'notices' => []
        ]);
    }
}
