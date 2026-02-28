<?php

namespace App\Services;

use App\Http\Resources\NotificationResource;
use App\Models\Tenant\Config\Template;
use App\Models\Tenant\Notification;
use App\Support\TemplateParser;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationService
{
    use TemplateParser;

    public function preRequisite(Request $request) {}

    public function paginate(Request $request)
    {
        $unreadCount = Notification::query()
            ->where('notifiable_type', '=', 'User')
            ->where('notifiable_id', '=', auth()->id())
            ->whereNull('read_at')
            ->count();

        $query = Notification::query()
            ->where('notifiable_type', '=', 'User')
            ->where('notifiable_id', '=', auth()->id())
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        if ($request->query('lastest')) {
            $notifications = $query->take(5)->get();
        } else {
            $notifications = $query->cursorPaginate(10);
        }

        $templateCodes = $notifications->pluck('meta')->pluck('template_code')->unique()->toArray();

        $templates = Template::query()
            ->where('type', '=', 'push')
            ->whereIn('code', $templateCodes)
            ->get();

        $notifications->map(function ($notification) use ($templates) {
            $template = $templates->firstWhere('code', $notification->getMeta('template_code'));

            if ($template) {
                $templateClone = clone $template;
                $template = $this->parseTemplate($templateClone, $notification->data);
            }

            $notification->subject = $template?->subject ?? $notification->getMeta('subject');
            $notification->content = $template?->content ?? $notification->getMeta('content');

            return $notification;
        });

        return NotificationResource::collection($notifications)->additional([
            'meta' => [
                'unread_count' => $unreadCount,
                'date' => \Cal::from(today())->showDetailedDate(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $uuid)
    {
        $notification = Notification::query()
            ->where('uuid', '=', $uuid)
            ->where('notifiable_type', '=', 'User')
            ->where('notifiable_id', '=', auth()->id())
            ->first();

        if (! $notification) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        $notification->update(['read_at' => now()->toDateTimeString()]);

        return response()->ok([]);
    }

    public function markAllAsRead(Request $request)
    {
        Notification::query()
            ->where('notifiable_type', '=', 'User')
            ->where('notifiable_id', '=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()->toDateTimeString()]);

        return response()->ok([]);
    }
}
