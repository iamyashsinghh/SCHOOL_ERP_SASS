<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student\Student;
use App\Services\Student\TransferApprovalRequestListService;
use App\Services\Student\TransferApprovalRequestService;
use Illuminate\Http\Request;

class TransferApprovalRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, TransferApprovalRequestService $service)
    {
        $this->authorize('transfer', Student::class);

        return response()->ok($service->preRequisite($request));
    }

    public function index(Request $request, TransferApprovalRequestListService $service)
    {
        $this->authorize('transfer', Student::class);

        return $service->paginate($request);
    }
}
