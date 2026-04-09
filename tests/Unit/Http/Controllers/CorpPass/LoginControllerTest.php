<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers\CorpPass;

use Accredifysg\SingPassLogin\Services\FapiAuthenticationService;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Mockery;

class LoginControllerTest extends TestCase
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
        Config::set('corppass-login.client_id', 'cp-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/cp-callback');
        Config::set('corppass-login.discovery_endpoint', 'https://corppass.example.com/discovery');
        Config::set('corppass-login.domain', 'https://corppass.example.com');
        Config::set('corppass-login.available_scopes', ['openid', 'entity.identity']);
        Config::set('corppass-login.login_scopes', ['openid', 'entity.identity']);

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function ($config, $scopes) {
                return $config->clientId === 'cp-client-id';
            })
            ->andReturn(['redirect_url' => 'https://corppass.example.com/auth?client_id=cp&request_uri=urn:test']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/cp/login');

        $response->assertStatus(200)
            ->assertJsonStructure(['redirect_url']);
    }

    public function test_it_passes_custom_scopes(): void
    {
        Config::set('corppass-login.client_id', 'cp-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/cp-callback');
        Config::set('corppass-login.discovery_endpoint', 'https://corppass.example.com/discovery');
        Config::set('corppass-login.domain', 'https://corppass.example.com');
        Config::set('corppass-login.available_scopes', ['openid', 'entity.identity', 'authinfo']);
        Config::set('corppass-login.login_scopes', ['openid', 'entity.identity']);

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function ($config, $scopes) {
                return $scopes === 'openid,entity.identity,authinfo';
            })
            ->andReturn(['redirect_url' => 'https://corppass.example.com/auth']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/cp/login?scopes=openid,entity.identity,authinfo');

        $response->assertStatus(200);
    }
}
