<?php

namespace App\Http\Controllers\Helpdesk\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\Ticket\TicketAssignRequest;
use App\Http\Requests\Helpdesk\Ticket\TicketMessageRequest;
use App\Models\Helpdesk\Ticket\Ticket;
use App\Services\Helpdesk\Ticket\TicketActionService;
use Illuminate\Http\Request;

class TicketActionController extends Controller
{
    public function assign(TicketAssignRequest $request, string $ticket, TicketActionService $service)
    {
        $ticket = Ticket::findByUuidOrFail($ticket);

        $this->authorize('action', $ticket);

        $service->assign($request, $ticket);

        return response()->success([
            'message' => trans('global.assigned', ['attribute' => trans('employee.employee')]),
        ]);
    }

    public function unassign(string $ticket, string $employee, TicketActionService $service)
    {
        $ticket = Ticket::findByUuidOrFail($ticket);

        $this->authorize('action', $ticket);

        $service->unassign($ticket, $employee);

        return response()->success([
            'message' => trans('global.unassigned', ['attribute' => trans('employee.employee')]),
        ]);
    }

    public function addMessage(TicketMessageRequest $request, string $ticket, TicketActionService $service)
    {
        $ticket = Ticket::findByUuidOrFail($ticket);

        $this->authorize('message', $ticket);

        $service->addMessage($request, $ticket);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('helpdesk.ticket.props.status')]),
        ]);
    }

    public function removeMessage(string $ticket, string $message, TicketActionService $service)
    {
        $ticket = Ticket::findByUuidOrFail($ticket);

        $this->authorize('action', $ticket);

        $service->removeMessage($ticket, $message);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('helpdesk.ticket.props.status')]),
        ]);
    }

    public function updateBulkAssignTo(Request $request, TicketActionService $service)
    {
        $this->authorize('bulkUpdate', Ticket::class);

        $service->updateBulkAssignTo($request);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('helpdesk.ticket.ticket')]),
        ]);
    }

    public function updateBulkCategory(Request $request, TicketActionService $service)
    {
        $this->authorize('bulkUpdate', Ticket::class);

        $service->updateBulkCategory($request);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('helpdesk.ticket.ticket')]),
        ]);
    }

    public function updateBulkPriority(Request $request, TicketActionService $service)
    {
        $this->authorize('bulkUpdate', Ticket::class);

        $service->updateBulkPriority($request);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('helpdesk.ticket.ticket')]),
        ]);
    }
}
