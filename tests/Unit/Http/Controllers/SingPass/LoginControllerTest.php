<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers\SingPass;

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
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.domain', 'https://example.com');
        Config::set('singpass-login.available_scopes', ['openid', 'name']);
        Config::set('singpass-login.login_scopes', ['openid']);

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->andReturn(['redirect_url' => 'https://example.com/auth?client_id=test&request_uri=urn:test']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/sp/login');

        $response->assertStatus(200)
            ->assertJsonStructure(['redirect_url']);
    }

    public function test_it_passes_auth_context_params(): void
    {
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.domain', 'https://example.com');
        Config::set('singpass-login.available_scopes', ['openid']);
        Config::set('singpass-login.login_scopes', ['openid']);
        Config::set('singpass-login.authentication_context_type', 'APP_AUTHENTICATION_DEFAULT');
        Config::set('singpass-login.authentication_context_message', null);

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function ($config, $scopes, $extra) {
                return isset($extra['authentication_context_type'])
                    && $extra['authentication_context_type'] === 'APP_AUTHENTICATION_DEFAULT';
            })
            ->andReturn(['redirect_url' => 'https://example.com/auth']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/sp/login');
        $response->assertStatus(200);
    }
}
