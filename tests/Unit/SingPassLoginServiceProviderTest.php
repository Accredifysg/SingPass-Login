<?php

namespace Accredifysg\SingPassLogin\Tests\Unit;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Listeners\SingPassSuccessfulLoginListener;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

class SingPassLoginServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [SingPassLoginServiceProvider::class];
    }

    public function testConfigIsPublished()
    {
        $this->artisan('vendor:publish', ['--provider' => 'Accredifysg\SingPassLogin\SingPassLoginServiceProvider', '--tag' => 'config']);

        $this->assertFileExists(config_path('SingPass-Login.php'));
    }

    public function testJwksIsPublished()
    {
        $this->artisan('vendor:publish', ['--provider' => 'Accredifysg\SingPassLogin\SingPassLoginServiceProvider', '--tag' => 'jwks']);

        $this->assertFileExists(storage_path('jwks/jwks.json'));
    }

    public function testRoutesAreLoaded()
    {
        $routeCollection = app('router')->getRoutes();

        $this->assertTrue($routeCollection->hasNamedRoute('singpass.callback'));
        $this->assertTrue($routeCollection->hasNamedRoute('singpass.jwks'));
    }

    public function testEventListenerIsRegistered()
    {
        Event::fake();

        Event::assertListening(
            SingPassSuccessfulLoginEvent::class,
            SingPassSuccessfulLoginListener::class
        );
    }

    public function testConfigIsMerged()
    {
        $this->assertNotNull(config('singpass-login'));

        $this->assertArrayHasKey('client_id', config('singpass-login'));
        $this->assertArrayHasKey('client_secret', config('singpass-login'));
        $this->assertArrayHasKey('redirect_uri', config('singpass-login'));
        $this->assertArrayHasKey('domain', config('singpass-login'));
        $this->assertArrayHasKey('discovery_endpoint', config('singpass-login'));
        $this->assertArrayHasKey('signing_kid', config('singpass-login'));
        $this->assertArrayHasKey('private_exponent', config('singpass-login'));
        $this->assertArrayHasKey('encryption_key', config('singpass-login'));
        $this->assertArrayHasKey('enable_default_singpass_routes', config('singpass-login'));
        $this->assertArrayHasKey('get_jwks_endpoint_url', config('singpass-login'));
        $this->assertArrayHasKey('post_singpass_callback_url', config('singpass-login'));
        $this->assertArrayHasKey('get_jwks_endpoint_controller', config('singpass-login'));
        $this->assertArrayHasKey('post_singpass_callback_controller', config('singpass-login'));
        $this->assertArrayHasKey('debug_mode', config('singpass-login'));
    }

    public function setUp(): void
    {
        parent::setUp();

        // Ensure we're starting with a clean slate
        if (File::exists(config_path('SingPass-Login.php'))) {
            File::delete(config_path('SingPass-Login.php'));
        }
        if (File::exists(storage_path('jwks/jwks.json'))) {
            File::delete(storage_path('jwks/jwks.json'));
        }
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        if (File::exists(config_path('SingPass-Login.php'))) {
            File::delete(config_path('SingPass-Login.php'));
        }
        if (File::exists(storage_path('jwks/jwks.json'))) {
            File::delete(storage_path('jwks/jwks.json'));
        }

        parent::tearDown();
    }
}