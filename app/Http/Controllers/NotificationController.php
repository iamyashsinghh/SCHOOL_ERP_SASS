<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $service)
    {
        return $service->paginate($request);
    }

    public function markAsRead(Request $request, string $uuid, NotificationService $service)
    {
        return $service->markAsRead($request, $uuid);
    }

    public function markAllAsRead(Request $request, NotificationService $service)
    {
        return $service->markAllAsRead($request);
    }
}
