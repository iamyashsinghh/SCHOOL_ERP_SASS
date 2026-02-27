<?php

namespace App\Http\Controllers\Site\View;

use App\Http\Controllers\Controller;
use App\Models\Communication\Announcement;
use App\Support\MarkdownParser;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    use MarkdownParser;

    public function __invoke(Request $request, string $slug, string $uuid)
    {
        $announcement = Announcement::query()
            ->with('type')
            ->whereUuid($uuid)
            ->where('is_public', true)
            ->firstOrFail();

        return view(config('config.site.view').'announcement', compact('announcement'));
    }
}
