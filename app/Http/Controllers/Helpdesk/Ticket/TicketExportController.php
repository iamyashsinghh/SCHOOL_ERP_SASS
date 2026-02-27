<?php

namespace App\Http\Controllers\Helpdesk\Ticket;

use App\Http\Controllers\Controller;
use App\Services\Helpdesk\Ticket\TicketListService;
use Illuminate\Http\Request;

class TicketExportController extends Controller
{
    public function __invoke(Request $request, TicketListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
