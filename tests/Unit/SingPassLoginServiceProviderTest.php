<?php

declare(strict_types=1);

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

        $this->assertFileExists(config_path('ndi.php'));
        $this->assertFileExists(config_path('singpass-login.php'));
        $this->assertFileExists(config_path('myinfo.php'));
        $this->assertFileExists(config_path('corppass-login.php'));
    }

    public function test_listener_is_published(): void
    {
        $this->artisan('vendor:publish', ['--provider' => 'Accredifysg\SingPassLogin\SingPassLoginServiceProvider', '--tag' => 'listener']);

        $this->assertFileExists(app_path('Listeners/SingPassSuccessfulLoginListener.php'));
    }

    public function test_routes_are_loaded(): void
    {
        $routeCollection = $this->routeCollection();

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
        $this->appConfigSet($app, 'singpass-login.enable_default_singpass_routes', false);
    }

    #[DefineEnvironment('disableSingpassRoutes')]
    public function test_singpass_routes_can_be_disabled(): void
    {
        $routeCollection = $this->routeCollection();

        $this->assertFalse($routeCollection->hasNamedRoute('singpass.login'));
        $this->assertFalse($routeCollection->hasNamedRoute('singpass.callback'));
        $this->assertTrue($routeCollection->hasNamedRoute('singpass.jwks'));
        $this->assertTrue($routeCollection->hasNamedRoute('myinfo.login'));
        $this->assertTrue($routeCollection->hasNamedRoute('corppass.login'));
    }

    protected function disableMyinfoRoutes(Application $app): void
    {
        $this->appConfigSet($app, 'myinfo.enable_default_myinfo_routes', false);
    }

    #[DefineEnvironment('disableMyinfoRoutes')]
    public function test_myinfo_routes_can_be_disabled(): void
    {
        $routeCollection = $this->routeCollection();

        $this->assertTrue($routeCollection->hasNamedRoute('singpass.login'));
        $this->assertFalse($routeCollection->hasNamedRoute('myinfo.login'));
        $this->assertFalse($routeCollection->hasNamedRoute('myinfo.callback'));
        $this->assertTrue($routeCollection->hasNamedRoute('corppass.login'));
    }

    protected function disableCorppassRoutes(Application $app): void
    {
        $this->appConfigSet($app, 'corppass-login.enable_default_corppass_routes', false);
    }

    #[DefineEnvironment('disableCorppassRoutes')]
    public function test_corppass_routes_can_be_disabled(): void
    {
        $routeCollection = $this->routeCollection();

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

    public function test_ndi_config_is_merged(): void
    {
        $this->assertNotNull(config('ndi'));

        $ndi = $this->configArray('ndi');
        $this->assertArrayHasKey('signing_kid', $ndi);
        $this->assertArrayHasKey('jwks', $ndi);
        $this->assertArrayHasKey('private_jwks', $ndi);
        $this->assertArrayHasKey('dpop_signing_algorithm', $ndi);
        $this->assertArrayHasKey('enable_logging', $ndi);
        $this->assertArrayHasKey('get_jwks_endpoint_url', $ndi);
        $this->assertArrayHasKey('get_jwks_endpoint_controller', $ndi);
    }

    public function test_singpass_config_is_merged(): void
    {
        $this->assertNotNull(config('singpass-login'));

        $singpass = $this->configArray('singpass-login');
        $this->assertArrayHasKey('client_id', $singpass);
        $this->assertArrayHasKey('redirect_uri', $singpass);
        $this->assertArrayHasKey('domain', $singpass);
        $this->assertArrayHasKey('discovery_endpoint', $singpass);
        $this->assertArrayHasKey('enable_default_singpass_routes', $singpass);
        $this->assertArrayHasKey('post_singpass_callback_url', $singpass);
        $this->assertArrayHasKey('post_singpass_callback_controller', $singpass);
        $this->assertArrayHasKey('use_default_listener', $singpass);
        $this->assertArrayHasKey('listener_class', $singpass);
        $this->assertArrayHasKey('authentication_context_type', $singpass);
        $this->assertArrayHasKey('authentication_context_message', $singpass);
        $this->assertArrayHasKey('login_scopes', $singpass);
    }

    public function test_myinfo_config_is_merged(): void
    {
        $this->assertNotNull(config('myinfo'));

        $myinfo = $this->configArray('myinfo');
        $this->assertArrayHasKey('client_id', $myinfo);
        $this->assertArrayHasKey('redirect_uri', $myinfo);
        $this->assertArrayHasKey('discovery_endpoint', $myinfo);
        $this->assertArrayHasKey('domain', $myinfo);
        $this->assertArrayHasKey('enable_default_myinfo_routes', $myinfo);
        $this->assertArrayHasKey('get_myinfo_authentication_endpoint_url', $myinfo);
        $this->assertArrayHasKey('get_myinfo_authentication_endpoint_controller', $myinfo);
        $this->assertArrayHasKey('post_myinfo_callback_url', $myinfo);
        $this->assertArrayHasKey('post_myinfo_callback_controller', $myinfo);
        $this->assertArrayHasKey('available_scopes', $myinfo);
        $this->assertArrayHasKey('login_scopes', $myinfo);
    }

    public function test_corppass_config_is_merged(): void
    {
        $this->assertNotNull(config('corppass-login'));

        $corppass = $this->configArray('corppass-login');
        $this->assertArrayHasKey('client_id', $corppass);
        $this->assertArrayHasKey('redirect_uri', $corppass);
        $this->assertArrayHasKey('domain', $corppass);
        $this->assertArrayHasKey('discovery_endpoint', $corppass);
        $this->assertArrayHasKey('enable_default_corppass_routes', $corppass);
        $this->assertArrayHasKey('login_scopes', $corppass);
        $this->assertArrayHasKey('available_scopes', $corppass);
        $this->assertArrayHasKey('use_default_listener', $corppass);
        $this->assertArrayHasKey('listener_class', $corppass);
    }

    public function test_corppass_config_is_published(): void
    {
        $this->artisan('vendor:publish', ['--provider' => 'Accredifysg\SingPassLogin\SingPassLoginServiceProvider', '--tag' => 'config']);

        $this->assertFileExists(config_path('corppass-login.php'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ndi.php', 'singpass-login.php', 'myinfo.php', 'corppass-login.php'] as $file) {
            if (File::exists(config_path($file))) {
                File::delete(config_path($file));
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (['ndi.php', 'singpass-login.php', 'myinfo.php', 'corppass-login.php'] as $file) {
            if (File::exists(config_path($file))) {
                File::delete(config_path($file));
            }
        }

        parent::tearDown();
    }
}
