<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Central\Dashboard;
use App\Livewire\Central\Auth\Login;
use Illuminate\Support\Facades\Auth;

Route::middleware(['web'])->group(function () {
    Route::get('/', function() {
        return redirect()->route('central.login');
    });

    Route::get('/login', Login::class)->name('central.login');

    Route::middleware(['auth:central'])->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('central.dashboard');
        Route::get('/ministries', \App\Livewire\Central\MinistryManager::class)->name('central.ministries');
        Route::get('/schools', \App\Livewire\Central\SchoolManager::class)->name('central.schools');
        Route::get('/users', \App\Livewire\Central\Users::class)->name('central.users');

        Route::get('/logout', function() {
            Auth::guard('central')->logout();
            return redirect()->route('central.login');
        })->name('central.logout');
    });
});
