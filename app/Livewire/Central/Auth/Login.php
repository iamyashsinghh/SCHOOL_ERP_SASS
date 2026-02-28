<?php

namespace App\Livewire\Central\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Login extends Component
{
    public $email;
    public $password;
    public $message;

    protected $rules = [
        'email' => 'required|email',
        'password' => 'required',
    ];

    public function login()
    {
        $this->validate();

        if (Auth::guard('central')->attempt(['email' => $this->email, 'password' => $this->password])) {
            return redirect()->route('central.dashboard');
        }

        $this->message = 'Invalid credentials. Please try again.';
    }

    public function render()
    {
        return view('livewire.central.auth.login')
            ->layout('layouts.central', ['title' => 'Central Login', 'header' => 'Governance Login']);
    }
}
