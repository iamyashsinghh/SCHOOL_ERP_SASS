<?php

namespace App\View\Components\Site;

use App\Enums\Blog\Status;
use App\Enums\Blog\Visibility;
use App\Models\Tenant\Blog\Blog;
use Illuminate\View\Component;

class BlogList extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(public $menu, public string $type = 'list')
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
        $query = Blog::query()
            ->with('category', 'tags')
            ->whereStatus(Status::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->toDateTimeString())
            ->whereVisibility(Visibility::PUBLIC)
            ->when(request()->category, function ($query, $category) {
                $query->whereHas('category', function ($query) use ($category) {
                    $query->whereSlug($category);
                });
            })
            ->when(request()->tag, function ($query, $tag) {
                $query->whereHas('tags', function ($query) use ($tag) {
                    $query->whereName($tag);
                });
            })
            ->orderBy('published_at', 'desc');

        if ($this->type == 'list') {
            $blogs = $query->paginate(9);
        } else {
            $blogs = $query->limit(3)->get();
        }

        return view()->first(['components.site.custom.blog-list', 'components.site.default.blog-list'], compact('blogs'));
    }
}
