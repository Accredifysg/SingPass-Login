<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers\SingPass;

use Accredifysg\SingPassLogin\Services\FapiAuthenticationService;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Mockery;

class MyInfoControllerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SingPassLoginServiceProvider::class];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_redirect_url(): void
    {
        Config::set('singpass-login.myinfo_client_id', 'myinfo-client-id');
        Config::set('singpass-login.myinfo_redirect_uri', 'https://example.com/myinfo-callback');
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.domain', 'https://example.com');
        Config::set('singpass-login.available_scopes', ['openid', 'uinfin']);
        Config::set('singpass-login.login_scopes', ['openid']);

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function ($config, $scopes) {
                return $config->clientId === 'myinfo-client-id';
            })
            ->andReturn(['redirect_url' => 'https://example.com/auth?client_id=myinfo&request_uri=urn:test']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/mi/initiate');

        $response->assertStatus(200)
            ->assertJsonStructure(['redirect_url']);
    }
}
