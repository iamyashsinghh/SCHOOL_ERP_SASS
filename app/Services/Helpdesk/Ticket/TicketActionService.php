<?php

namespace App\Services\Helpdesk\Ticket;

use App\Enums\Helpdesk\Ticket\Status as TicketStatus;
use App\Enums\OptionType;
use App\Jobs\Notifications\Helpdesk\Ticket\SendTicketAssignedNotification;
use App\Jobs\Notifications\Helpdesk\Ticket\SendTicketRepliedNotification;
use App\Models\Employee\Employee;
use App\Models\Helpdesk\Ticket\Assignee;
use App\Models\Helpdesk\Ticket\Message as TicketMessage;
use App\Models\Helpdesk\Ticket\Ticket;
use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TicketActionService
{
    public function ensureIsEditable(Ticket $ticket)
    {
        if (! $ticket->isEditable()) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $this->ensureIsEditable($ticket);

        Assignee::firstOrCreate([
            'ticket_id' => $ticket->id,
            'user_id' => $request->employee_user_id,
        ]);

        SendTicketAssignedNotification::dispatch([
            'ticket_id' => $ticket->id,
            'employee_id' => $request->employee_id,
            'team_id' => auth()->user()->current_team_id,
        ]);
    }

    public function unassign(Ticket $ticket, string $employee)
    {
        $this->ensureIsEditable($ticket);

        $employee = Employee::query()
            ->with('contact')
            ->byTeam()
            ->where('uuid', $employee)
            ->getOrFail(trans('employee.employee'));

        Assignee::where('user_id', $employee?->contact?->user_id)
            ->where('ticket_id', $ticket->id)
            ->delete();
    }

    public function addMessage(Request $request, Ticket $ticket)
    {
        $this->ensureIsEditable($ticket);

        \DB::beginTransaction();

        $message = TicketMessage::forceCreate([
            'ticket_id' => $ticket->id,
            'status' => $request->status,
            'message' => $request->message,
            'user_id' => auth()->id(),
        ]);

        if ($request->status == TicketStatus::RESOLVED->value) {
            $ticket->resolved_at = now()->toDateTimeString();
        } else {
            $ticket->resolved_at = null;
        }

        $ticket->status = $request->status;

        $ticket->save();

        $message->addMedia($request);

        \DB::commit();

        SendTicketRepliedNotification::dispatch([
            'ticket_id' => $ticket->id,
            'message_id' => $message->id,
            'team_id' => auth()->user()->current_team_id,
        ]);
    }

    private function isEditable(Ticket $ticket, TicketMessage $message): bool
    {
        $this->ensureIsEditable($ticket);

        if (! $message->is_editable) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        $messages = TicketMessage::query()
            ->whereTicketId($ticket->id)
            ->where('id', '>', $message->id)
            ->get();

        if ($messages->count()) {
            throw ValidationException::withMessages(['message' => trans('helpdesk.ticket.could_not_modify_if_not_last_message')]);
        }

        if (! auth()->user()->hasRole('admin') && $ticket->user_id != auth()->id()) {
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        return true;
    }

    public function removeMessage(Ticket $ticket, string $message)
    {
        $this->ensureIsEditable($ticket);

        $message = TicketMessage::query()
            ->whereTicketId($ticket->id)
            ->whereUuid($message)
            ->getOrFail(trans('helpdesk.ticket.props.message'));

        $this->isEditable($ticket, $message);

        \DB::beginTransaction();

        $message->delete();

        $lastMessage = TicketMessage::query()
            ->whereTicketId($ticket->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastMessage) {
            $ticket->resolved_at = null;
            $ticket->status = $lastMessage?->status;
        } elseif ($lastMessage?->status == TicketStatus::RESOLVED) {
            $ticket->resolved_at = $lastMessage->created_at;
            $ticket->status = TicketStatus::RESOLVED;
        } else {
            $ticket->resolved_at = null;
            $ticket->status = $lastMessage->status;
        }

        $ticket->save();

        \DB::commit();
    }

    public function updateBulkAssignTo(Request $request)
    {
        $request->validate([
            'tickets' => 'array',
            'employee' => 'required|uuid',
        ]);

        $employee = Employee::query()
            ->byTeam()
            ->whereUuid($request->input('employee'))
            ->getOrFail(__('employee.employee'), 'employee');

        $tickets = Ticket::query()
            ->filterAccessible()
            ->whereIn('uuid', $request->input('tickets', []))
            ->get();

        foreach ($tickets as $ticket) {
            // $ticket->employee_id = $employee->id;
            // $ticket->save();
        }
    }

    public function updateBulkCategory(Request $request)
    {
        $request->validate([
            'tickets' => 'array',
            'category' => 'required|uuid',
        ]);

        $category = Option::query()
            ->byTeam()
            ->where('type', OptionType::TICKET_CATEGORY)
            ->whereUuid($request->input('category'))
            ->first();

        if (! $category) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $tickets = Ticket::query()
            ->filterAccessible()
            ->whereIn('uuid', $request->input('tickets', []))
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->category_id = $category->id;
            $ticket->save();
        }
    }

    public function updateBulkPriority(Request $request)
    {
        $request->validate([
            'tickets' => 'array',
            'priority' => 'required|uuid',
        ]);

        $priority = Option::query()
            ->byTeam()
            ->where('type', OptionType::TICKET_PRIORITY)
            ->whereUuid($request->input('priority'))
            ->first();

        if (! $priority) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_input')]);
        }

        $tickets = Ticket::query()
            ->filterAccessible()
            ->whereIn('uuid', $request->input('tickets', []))
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->priority_id = $priority->id;
            $ticket->save();
        }
    }
}
