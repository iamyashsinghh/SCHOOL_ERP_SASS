<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reception\OnlineEnquiryRequest;
use App\Models\Tenant\Team;
use App\Services\Reception\OnlineEnquiryService;
use Illuminate\Http\Request;

class OnlineEnquiryController extends Controller
{
    public function preRequisite(Request $request, OnlineEnquiryService $service)
    {
        return $service->preRequisite($request);
    }

    public function getPrograms(Team $team, OnlineEnquiryService $service)
    {
        return $service->getPrograms($team);
    }

    public function getPeriods(Team $team, OnlineEnquiryService $service)
    {
        return $service->getPeriods($team);
    }

    public function getCourses(string $period, OnlineEnquiryService $service)
    {
        return $service->getCourses($period);
    }

    public function getBatches(string $period, string $course, OnlineEnquiryService $service)
    {
        return $service->getBatches($period, $course);
    }

    public function create(OnlineEnquiryRequest $request, OnlineEnquiryService $service)
    {
        $enquiry = $service->create($request);

        return response()->success([
            'message' => trans('reception.online_enquiry.received'),
        ]);
    }
}
