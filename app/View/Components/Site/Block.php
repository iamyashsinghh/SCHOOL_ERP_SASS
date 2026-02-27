<?php

namespace App\View\Components\Site;

use Illuminate\View\Component;

class Block extends Component
{
    public $block;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($block)
    {
        $this->block = $block;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view()->first(['components.site.custom.block', 'components.site.default.block']);
    }
}
