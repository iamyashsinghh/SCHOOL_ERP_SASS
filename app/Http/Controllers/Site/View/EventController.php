<?php

namespace App\Http\Controllers\Site\View;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Calendar\Event;
use App\Support\MarkdownParser;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use MarkdownParser;

    public function __invoke(Request $request, string $slug, string $uuid)
    {
        $event = Event::query()
            ->with('type')
            ->whereUuid($uuid)
            ->where('is_public', true)
            ->firstOrFail();

        return view(config('config.site.view').'event', compact('event'));
    }
}
