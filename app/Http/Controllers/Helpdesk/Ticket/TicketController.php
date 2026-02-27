<?php

namespace App\Http\Controllers\Helpdesk\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\Ticket\TicketRequest;
use App\Http\Resources\Helpdesk\Ticket\TicketResource;
use App\Models\Helpdesk\Ticket\Ticket;
use App\Services\Helpdesk\Ticket\TicketListService;
use App\Services\Helpdesk\Ticket\TicketService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(Request $request, TicketService $service)
    {
        $this->authorize('preRequisite', Ticket::class);

        return response()->ok($service->preRequisite($request));
    }

    public function index(Request $request, TicketListService $service)
    {
        $this->authorize('viewAny', Ticket::class);

        return $service->paginate($request);
    }

    public function store(TicketRequest $request, TicketService $service)
    {
        $this->authorize('create', Ticket::class);

        $ticket = $service->create($request);

        $ticket->addMedia($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('helpdesk.ticket.ticket')]),
            'ticket' => TicketResource::make($ticket),
        ]);
    }

    public function show(Request $request, string $ticket, TicketService $service)
    {
        $ticket = Ticket::findByUuidOrFail($ticket);

        $this->authorize('view', $ticket);

        $ticket->load([
            'assignees', 'messages.media', 'priority', 'category', 'media', 'tags',
        ]);

        $employees = $service->getEmployees($ticket);

        $request->merge([
            'detail' => true,
            'has_custom_fields' => true,
            'employees' => $employees,
        ]);

        return TicketResource::make($ticket);
    }

    public function update(TicketRequest $request, string $ticket, TicketService $service)
    {
        $ticket = Ticket::findByUuidOrFail($ticket);

        $this->authorize('update', $ticket);

        $service->update($request, $ticket);

        $ticket->updateMedia($request);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('helpdesk.ticket.ticket')]),
        ]);
    }

    public function destroy(string $ticket, TicketService $service)
    {
        $ticket = Ticket::findByUuidOrFail($ticket);

        $this->authorize('delete', $ticket);

        $service->deletable($ticket);

        $ticket->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('helpdesk.ticket.ticket')]),
        ]);
    }

    public function destroyMultiple(Request $request, TicketService $service)
    {
        $this->authorize('delete', Ticket::class);

        $count = $service->deleteMultiple($request);

        return response()->success([
            'message' => trans('global.multiple_deleted', ['count' => $count, 'attribute' => trans('helpdesk.ticket.ticket')]),
        ]);
    }

    public function downloadMedia(string $ticket, string $uuid, TicketService $service)
    {
        $ticket = Ticket::findByUuidOrFail($ticket);

        $this->authorize('view', $ticket);

        return $ticket->downloadMedia($uuid);
    }

    public function downloadMessageMedia(string $ticket, string $message, string $uuid, TicketService $service)
    {
        $ticket = Ticket::findByUuidOrFail($ticket);

        $this->authorize('view', $ticket);

        $message = $service->getMessage($ticket, $message);

        return $message->downloadMedia($uuid);
    }
}
