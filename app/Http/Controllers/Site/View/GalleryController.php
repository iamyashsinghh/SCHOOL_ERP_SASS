<?php

namespace App\Http\Controllers\Site\View;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function __invoke(Request $request, string $slug, string $uuid)
    {
        $gallery = Gallery::query()
            ->with('images')
            ->whereUuid($uuid)
            ->where('is_public', true)
            ->firstOrFail();

        return view(config('config.site.view').'gallery', compact('gallery'));
    }
}
