<?php

namespace Accredifysg\SingPassLogin;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassTokenServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassLoginInterface;
use Accredifysg\SingPassLogin\Services\GetSingPassJwksService;
use Accredifysg\SingPassLogin\Services\GetSingPassTokenService;
use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
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
            __DIR__.'/../config/singpass-login.php' => config_path('singpass-login.php'),
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
        $this->app->bind(GetSingPassJwksServiceInterface::class, GetSingPassJwksService::class);
        $this->app->bind(GetSingPassTokenServiceInterface::class, GetSingPassTokenService::class);
        $this->app->bind(OpenIdDiscoveryServiceInterface::class, OpenIdDiscoveryService::class);
        $this->app->bind(SingPassJwtServiceInterface::class, SingPassJwtService::class);
        $this->app->bind(SingPassLoginInterface::class, SingPassLogin::class);

        // Merge configuration file
        $this->mergeConfigFrom(
            __DIR__.'/../config/singpass-login.php',
            'singpass-login'
        );
    }
}
