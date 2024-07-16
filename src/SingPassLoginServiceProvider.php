<?php

namespace Accredifysg\SingPassLogin;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Listeners\SingPassSuccessfulLoginListener;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class SingPassLoginServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../config/SingPass-Login.php' => config_path('SingPass-Login.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../jwks/jwks.json' => storage_path('jwks/jwks.json'),
        ], 'jwks');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Register event and listener
        Event::listen(
            SingPassSuccessfulLoginEvent::class,
            SingPassSuccessfulLoginListener::class
        );
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge configuration file
        $this->mergeConfigFrom(
            __DIR__ . '/../config/SingPass-Login.php',
            'singpass-login'
        );
    }
}
