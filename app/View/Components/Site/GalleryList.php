<?php

namespace App\View\Components\Site;

use App\Models\Gallery;
use Illuminate\View\Component;

class GalleryList extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public string $type = 'list')
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        $galleries = Gallery::query()
            ->select('galleries.*', 'teams.name as team_name')
            ->join('teams', 'galleries.team_id', '=', 'teams.id')
            ->withThumbnail()
            ->has('images')
            ->where('is_public', true)
            ->orderBy('date', 'desc')
            ->limit($this->type == 'list' ? 10 : 3)
            ->get()
            ->groupBy('team_name');

        return view()->first(['components.site.custom.gallery-list', 'components.site.default.gallery-list'], compact('galleries'));
    }
}
