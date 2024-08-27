<?php

namespace Accredifysg\SingPassLogin;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
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

        // Publish listener
        $this->publishes([
            __DIR__.'/Listeners/SingPassSuccessfulLoginListener.php' => app_path('Listeners/SingPassSuccessfulLoginListener.php'),
        ], 'listener');

        // Register event and listener
        $this->registerEventListener();

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    protected function registerEventListener(): void
    {
        if (config('singpass-login.use_default_listener')) {
            Event::listen(
                SingPassSuccessfulLoginEvent::class,
                config('singpass-login.listener_class')
            );
        }
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
