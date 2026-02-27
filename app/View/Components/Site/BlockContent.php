<?php

namespace App\View\Components\Site;

use Illuminate\View\Component;

class BlockContent extends Component
{
    public $block;

    public $fullHeight;

    public $itemCount;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($block, $fullHeight = false, $itemCount = 1)
    {
        $this->block = $block;
        $this->fullHeight = $fullHeight;
        $this->itemCount = $itemCount;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view()->first(['components.site.custom.block-content', 'components.site.default.block-content']);
    }
}
