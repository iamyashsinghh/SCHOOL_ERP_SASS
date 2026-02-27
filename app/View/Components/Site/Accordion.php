<?php

namespace App\View\Components\Site;

use Illuminate\View\Component;

class Accordion extends Component
{
    public $items;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($items)
    {
        $this->items = $items;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view()->first(['components.site.custom.accordion', 'components.site.default.accordion']);
    }
}
