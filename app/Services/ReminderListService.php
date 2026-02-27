<?php

namespace App\Services;

use App\Contracts\ListGenerator;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReminderListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'date'];

    protected $defaultSort = 'date';

    protected $defaultOrder = 'desc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'title',
                'label' => trans('reminder.props.title'),
                'print_label' => 'title',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'date',
                'label' => trans('reminder.props.date'),
                'print_label' => 'date.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'notifyBefore',
                'label' => trans('reminder.props.notify_before'),
                'print_label' => 'notify_before',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'usersCount',
                'label' => trans('reminder.props.users_count'),
                'print_label' => 'users_count',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'createdAt',
                'label' => trans('general.created_at'),
                'print_label' => 'created_at.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
        ];

        if (request()->ajax()) {
            $headers[] = $this->actionHeader;
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        return Reminder::query()
            ->withCount('users')
            ->where(function ($q) {
                $q->where('user_id', auth()->id())
                    ->orWhereHas('users', function ($q) {
                        $q->where('user_id', auth()->id());
                    });
            })
            ->filter([]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        return ReminderResource::collection($this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page'))
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
