<?php

namespace Accredifysg\SingPassLogin\Tests\Unit;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Listeners\SingPassSuccessfulLoginListener;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class SingPassLoginServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SingPassLoginServiceProvider::class];
    }

    public function test_config_is_published(): void
    {
        $this->artisan('vendor:publish', ['--provider' => 'Accredifysg\SingPassLogin\SingPassLoginServiceProvider', '--tag' => 'config']);

        $this->assertFileExists(config_path('singpass-login.php'));
    }

    public function test_listener_is_published(): void
    {
        $this->artisan('vendor:publish', ['--provider' => 'Accredifysg\SingPassLogin\SingPassLoginServiceProvider', '--tag' => 'listener']);

        $this->assertFileExists(app_path('Listeners/SingPassSuccessfulLoginListener.php'));
    }

    public function test_routes_are_loaded(): void
    {
        $routeCollection = app('router')->getRoutes();

        $this->assertTrue($routeCollection->hasNamedRoute('singpass.login'));
        $this->assertTrue($routeCollection->hasNamedRoute('singpass.callback'));
        $this->assertTrue($routeCollection->hasNamedRoute('singpass.jwks'));
        $this->assertTrue($routeCollection->hasNamedRoute('myinfo.login'));
        $this->assertTrue($routeCollection->hasNamedRoute('myinfo.callback'));
        $this->assertTrue($routeCollection->hasNamedRoute('corppass.login'));
        $this->assertTrue($routeCollection->hasNamedRoute('corppass.callback'));
    }

    protected function disableSingpassRoutes(Application $app): void
    {
        $app['config']->set('singpass-login.enable_default_singpass_routes', false);
    }

    #[DefineEnvironment('disableSingpassRoutes')]
    public function test_singpass_routes_can_be_disabled(): void
    {
        $routeCollection = app('router')->getRoutes();

        $this->assertFalse($routeCollection->hasNamedRoute('singpass.login'));
        $this->assertFalse($routeCollection->hasNamedRoute('singpass.callback'));
        $this->assertTrue($routeCollection->hasNamedRoute('singpass.jwks'));
        $this->assertTrue($routeCollection->hasNamedRoute('myinfo.login'));
        $this->assertTrue($routeCollection->hasNamedRoute('corppass.login'));
    }

    protected function disableMyinfoRoutes(Application $app): void
    {
        $app['config']->set('singpass-login.enable_default_myinfo_routes', false);
    }

    #[DefineEnvironment('disableMyinfoRoutes')]
    public function test_myinfo_routes_can_be_disabled(): void
    {
        $routeCollection = app('router')->getRoutes();

        $this->assertTrue($routeCollection->hasNamedRoute('singpass.login'));
        $this->assertFalse($routeCollection->hasNamedRoute('myinfo.login'));
        $this->assertFalse($routeCollection->hasNamedRoute('myinfo.callback'));
        $this->assertTrue($routeCollection->hasNamedRoute('corppass.login'));
    }

    protected function disableCorppassRoutes(Application $app): void
    {
        $app['config']->set('corppass-login.enable_default_corppass_routes', false);
    }

    #[DefineEnvironment('disableCorppassRoutes')]
    public function test_corppass_routes_can_be_disabled(): void
    {
        $routeCollection = app('router')->getRoutes();

        $this->assertTrue($routeCollection->hasNamedRoute('singpass.login'));
        $this->assertTrue($routeCollection->hasNamedRoute('myinfo.login'));
        $this->assertFalse($routeCollection->hasNamedRoute('corppass.login'));
        $this->assertFalse($routeCollection->hasNamedRoute('corppass.callback'));
    }

    public function test_event_listener_is_registered(): void
    {
        Event::fake();

        Event::assertListening(
            SingPassSuccessfulLoginEvent::class,
            SingPassSuccessfulLoginListener::class
        );
    }

    public function test_config_is_merged(): void
    {
        $this->assertNotNull(config('singpass-login'));

        $this->assertArrayHasKey('client_id', config('singpass-login'));
        $this->assertArrayHasKey('redirect_uri', config('singpass-login'));
        $this->assertArrayHasKey('domain', config('singpass-login'));
        $this->assertArrayHasKey('discovery_endpoint', config('singpass-login'));
        $this->assertArrayHasKey('signing_kid', config('singpass-login'));
        $this->assertArrayHasKey('jwks', config('singpass-login'));
        $this->assertArrayHasKey('private_jwks', config('singpass-login'));
        $this->assertArrayHasKey('enable_default_singpass_routes', config('singpass-login'));
        $this->assertArrayHasKey('enable_default_myinfo_routes', config('singpass-login'));
        $this->assertArrayHasKey('get_jwks_endpoint_url', config('singpass-login'));
        $this->assertArrayHasKey('post_singpass_callback_url', config('singpass-login'));
        $this->assertArrayHasKey('get_jwks_endpoint_controller', config('singpass-login'));
        $this->assertArrayHasKey('post_singpass_callback_controller', config('singpass-login'));
        $this->assertArrayHasKey('use_default_listener', config('singpass-login'));
        $this->assertArrayHasKey('listener_class', config('singpass-login'));
        $this->assertArrayHasKey('myinfo_client_id', config('singpass-login'));
        $this->assertArrayHasKey('myinfo_redirect_uri', config('singpass-login'));
        $this->assertArrayHasKey('dpop_signing_algorithm', config('singpass-login'));
        $this->assertArrayHasKey('authentication_context_type', config('singpass-login'));
        $this->assertArrayHasKey('authentication_context_message', config('singpass-login'));
        $this->assertArrayHasKey('login_scopes', config('singpass-login'));
        $this->assertArrayHasKey('get_myinfo_authentication_endpoint_url', config('singpass-login'));
        $this->assertArrayHasKey('get_myinfo_authentication_endpoint_controller', config('singpass-login'));
        $this->assertArrayHasKey('post_myinfo_callback_url', config('singpass-login'));
        $this->assertArrayHasKey('post_myinfo_callback_controller', config('singpass-login'));
    }

    public function test_corppass_config_is_merged(): void
    {
        $this->assertNotNull(config('corppass-login'));

        $this->assertArrayHasKey('client_id', config('corppass-login'));
        $this->assertArrayHasKey('redirect_uri', config('corppass-login'));
        $this->assertArrayHasKey('domain', config('corppass-login'));
        $this->assertArrayHasKey('discovery_endpoint', config('corppass-login'));
        $this->assertArrayHasKey('enable_default_corppass_routes', config('corppass-login'));
        $this->assertArrayHasKey('login_scopes', config('corppass-login'));
        $this->assertArrayHasKey('available_scopes', config('corppass-login'));
        $this->assertArrayHasKey('use_default_listener', config('corppass-login'));
        $this->assertArrayHasKey('listener_class', config('corppass-login'));
    }

    public function test_corppass_config_is_published(): void
    {
        $this->artisan('vendor:publish', ['--provider' => 'Accredifysg\SingPassLogin\SingPassLoginServiceProvider', '--tag' => 'config']);

        $this->assertFileExists(config_path('corppass-login.php'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (File::exists(config_path('singpass-login.php'))) {
            File::delete(config_path('singpass-login.php'));
        }
        if (File::exists(config_path('corppass-login.php'))) {
            File::delete(config_path('corppass-login.php'));
        }
    }

    protected function tearDown(): void
    {
        if (File::exists(config_path('singpass-login.php'))) {
            File::delete(config_path('singpass-login.php'));
        }
        if (File::exists(config_path('corppass-login.php'))) {
            File::delete(config_path('corppass-login.php'));
        }

        parent::tearDown();
    }
}
