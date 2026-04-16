<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin;

use Accredifysg\SingPassLogin\Events\CorpPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetUserInfoServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\JwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\JwtServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\PushedAuthorizationRequestServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\TokenExchangeServiceInterface;
use Accredifysg\SingPassLogin\Services\DPoPService;
use Accredifysg\SingPassLogin\Services\FapiAuthenticationService;
use Accredifysg\SingPassLogin\Services\FapiCallbackService;
use Accredifysg\SingPassLogin\Services\GetUserInfoService;
use Accredifysg\SingPassLogin\Services\JwksService;
use Accredifysg\SingPassLogin\Services\JwtService;
use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
use Accredifysg\SingPassLogin\Services\PushedAuthorizationRequestService;
use Accredifysg\SingPassLogin\Services\TokenExchangeService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SingPassLoginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/ndi.php' => config_path('ndi.php'),
            __DIR__.'/../config/singpass-login.php' => config_path('singpass-login.php'),
            __DIR__.'/../config/myinfo.php' => config_path('myinfo.php'),
            __DIR__.'/../config/corppass-login.php' => config_path('corppass-login.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/Listeners/SingPassSuccessfulLoginListener.php' => app_path('Listeners/SingPassSuccessfulLoginListener.php'),
            __DIR__.'/Listeners/CorpPassSuccessfulLoginListener.php' => app_path('Listeners/CorpPassSuccessfulLoginListener.php'),
        ], 'listener');

        $this->registerEventListeners();

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    protected function registerEventListeners(): void
    {
        if (config('singpass-login.use_default_listener')) {
            $singPassListener = config('singpass-login.listener_class');
            if (is_string($singPassListener) && $singPassListener !== '') {
                Event::listen(
                    SingPassSuccessfulLoginEvent::class,
                    $singPassListener
                );
            }
        }

        if (config('corppass-login.use_default_listener')) {
            $corpPassListener = config('corppass-login.listener_class');
            if (is_string($corpPassListener) && $corpPassListener !== '') {
                Event::listen(
                    CorpPassSuccessfulLoginEvent::class,
                    $corpPassListener
                );
            }
        }
    }

    public function register(): void
    {
        $this->app->singleton(DPoPServiceInterface::class, DPoPService::class);
        $this->app->singleton(PushedAuthorizationRequestServiceInterface::class, PushedAuthorizationRequestService::class);
        $this->app->singleton(JwksServiceInterface::class, JwksService::class);
        $this->app->singleton(TokenExchangeServiceInterface::class, TokenExchangeService::class);
        $this->app->singleton(OpenIdDiscoveryServiceInterface::class, OpenIdDiscoveryService::class);
        $this->app->singleton(JwtServiceInterface::class, JwtService::class);
        $this->app->singleton(GetUserInfoServiceInterface::class, GetUserInfoService::class);
        $this->app->singleton(FapiAuthenticationService::class);
        $this->app->singleton(FapiCallbackService::class);

        $this->mergeConfigFrom(__DIR__.'/../config/ndi.php', 'ndi');
        $this->mergeConfigFrom(__DIR__.'/../config/singpass-login.php', 'singpass-login');
        $this->mergeConfigFrom(__DIR__.'/../config/myinfo.php', 'myinfo');
        $this->mergeConfigFrom(__DIR__.'/../config/corppass-login.php', 'corppass-login');
    }
}
