<?php

namespace App\Services\Helpdesk\Ticket;

use App\Actions\CreateTag;
use App\Enums\CustomFieldForm;
use App\Enums\Helpdesk\Ticket\Status as TicketStatus;
use App\Enums\OptionType;
use App\Http\Resources\CustomFieldResource;
use App\Http\Resources\OptionResource;
use App\Jobs\Notifications\Helpdesk\Ticket\SendTicketRaisedNotification;
use App\Models\CustomField;
use App\Models\Employee\Employee;
use App\Models\Helpdesk\Ticket\Message as TicketMessage;
use App\Models\Helpdesk\Ticket\Ticket;
use App\Models\Option;
use App\Models\User;
use App\Support\FormatCodeNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TicketService
{
    use FormatCodeNumber;

    private function codeNumber(): array
    {
        $numberPrefix = config('config.helpdesk.ticket_number_prefix');
        $numberSuffix = config('config.helpdesk.ticket_number_suffix');
        $digit = config('config.helpdesk.ticket_number_digit', 0);

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $numberFormat = $this->preFormatForDate($numberFormat);

        $codeNumber = (int) Ticket::query()
            ->byTeam()
            ->whereNumberFormat($numberFormat)
            ->max('number') + 1;

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }

    public function preRequisite(Request $request): array
    {
        $categories = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::TICKET_CATEGORY->value)
            ->orderBy('meta->position', 'asc')
            ->get());

        $priorities = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::TICKET_PRIORITY->value)
            ->orderBy('meta->position', 'asc')
            ->get());

        $lists = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::TICKET_LIST->value)
            ->orderBy('meta->position', 'asc')
            ->get());

        $statuses = TicketStatus::getOptions();

        $ticketLists = $lists;

        $customFields = CustomFieldResource::collection(CustomField::query()
            ->byTeam()
            ->where('form', CustomFieldForm::TICKET->value)
            ->get());

        return compact('lists', 'categories', 'priorities', 'ticketLists', 'customFields', 'statuses');
    }

    public function getEmployees(Ticket $ticket): Collection
    {
        $assignees = $ticket->assignees->pluck('user_id');
        $assignees->push($ticket->user_id);

        $employees = Employee::query()
            ->summary()
            ->whereIn('user_id', $assignees)
            ->get();

        return $employees;
    }

    public function getMessage(Ticket $ticket, string $message): TicketMessage
    {
        return $ticket->messages()->where('uuid', $message)->firstOrFail();
    }

    public function create(Request $request): Ticket
    {
        \DB::beginTransaction();

        $ticket = Ticket::forceCreate($this->formatParams($request));

        $tags = (new CreateTag)->execute($request->input('tags', []));

        $ticket->tags()->sync($tags);

        \DB::commit();

        $users = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })
            ->get();

        foreach ($users as $user) {
            SendTicketRaisedNotification::dispatch([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'team_id' => auth()->user()->current_team_id,
            ]);
        }

        return $ticket;
    }

    private function formatParams(Request $request, ?Ticket $ticket = null): array
    {
        $formatted = [
            'category_id' => $request->category_id,
            'priority_id' => $request->priority_id,
            'title' => $request->title,
            'description' => $request->description,
        ];

        if (! $ticket) {
            $employee = Employee::query()
                ->auth()
                ->first();

            $codeNumberDetail = $this->codeNumber();

            $formatted['number_format'] = Arr::get($codeNumberDetail, 'number_format');
            $formatted['number'] = Arr::get($codeNumberDetail, 'number');
            $formatted['code_number'] = Arr::get($codeNumberDetail, 'code_number');
            $formatted['user_id'] = $employee?->user_id;
            $formatted['status'] = TicketStatus::OPEN;
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        return $formatted;
    }

    public function update(Request $request, Ticket $ticket): void
    {
        if ($ticket->status != TicketStatus::OPEN->value) {
            throw ValidationException::withMessages(['message' => trans('helpdesk.ticket.could_not_update_if_not_open')]);
        }

        \DB::beginTransaction();

        $ticket->forceFill($this->formatParams($request, $ticket))->save();

        $tags = (new CreateTag)->execute($request->input('tags', []));

        $ticket->tags()->sync($tags);

        \DB::commit();
    }

    public function deletable(Ticket $ticket, $validate = false): ?bool
    {
        if ($ticket->status != TicketStatus::OPEN) {
            if ($validate) {
                return false;
            }
            throw ValidationException::withMessages(['message' => trans('user.errors.permission_denied')]);
        }

        return true;
    }

    private function findMultiple(Request $request): array
    {
        if ($request->boolean('global')) {
            $listService = new TicketListService;
            $uuids = $listService->getIds($request);
        } else {
            $uuids = is_array($request->uuids) ? $request->uuids : [];
        }

        if (! count($uuids)) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('general.data')])]);
        }

        return $uuids;
    }

    public function deleteMultiple(Request $request): int
    {
        $uuids = $this->findMultiple($request);

        $tickets = Ticket::query()
            ->filterAccessible()
            ->whereIn('uuid', $uuids)
            ->get();

        $deletable = [];
        foreach ($tickets as $ticket) {
            if ($this->deletable($ticket, true)) {
                $deletable[] = $ticket->uuid;
            }
        }

        if (! count($deletable)) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_delete_any', ['attribute' => trans('helpdesk.ticket.ticket')])]);
        }

        Ticket::query()
            ->whereIn('uuid', $deletable)->delete();

        return count($deletable);
    }
}
