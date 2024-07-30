<?php

namespace Accredifysg\SingPassLogin;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Listeners\SingPassSuccessfulLoginListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SingPassLoginServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__.'/../config/SingPass-Login.php' => config_path('SingPass-Login.php'),
        ], 'config');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Register event and listener
        Event::listen(
            SingPassSuccessfulLoginEvent::class,
            SingPassSuccessfulLoginListener::class
        );
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge configuration file
        $this->mergeConfigFrom(
            __DIR__.'/../config/SingPass-Login.php',
            'singpass-login'
        );
    }
}
