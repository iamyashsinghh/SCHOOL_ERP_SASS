<?php

namespace App\Providers;

use App\Events\Auth\UserLogin;
use App\Listeners\UserLoginListener;
use App\Models\Tenant\Academic\Period;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Team;
use App\Models\Tenant\User;
use App\Observers\Academic\PeriodObserver;
use App\Observers\ContactObserver;
use App\Observers\TeamObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        UserLogin::class => [
            UserLoginListener::class,
        ],
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \SocialiteProviders\Manager\SocialiteWasCalled::class => [
            // ... other providers
            \SocialiteProviders\Microsoft\MicrosoftExtendSocialite::class.'@handle',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        Team::observe(TeamObserver::class);
        User::observe(UserObserver::class);
        Contact::observe(ContactObserver::class);
        // Period::observe(PeriodObserver::class);
    }
}
