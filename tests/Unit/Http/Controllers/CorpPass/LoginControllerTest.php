<?php

declare(strict_types=1);

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
        Config::set('corppass-login.client_id', 'test-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/callback');
        Config::set('corppass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('corppass-login.domain', 'https://example.com');
        Config::set('corppass-login.available_scopes', ['openid', 'entity.identity']);
        Config::set('corppass-login.login_scopes', ['openid']);

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->andReturn(['redirect_url' => 'https://example.com/auth?client_id=test&request_uri=urn:test']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/cp/login');

        $response->assertStatus(200)
            ->assertJsonStructure(['redirect_url']);
    }

    public function test_it_passes_auth_context_params_from_config(): void
    {
        Config::set('corppass-login.client_id', 'test-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/callback');
        Config::set('corppass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('corppass-login.domain', 'https://example.com');
        Config::set('corppass-login.available_scopes', ['openid']);
        Config::set('corppass-login.login_scopes', ['openid']);
        Config::set('corppass-login.authentication_context_type', 'APP_AUTHENTICATION_DEFAULT');

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function ($config, $scopes, $extra) {
                return isset($extra['authentication_context_type'])
                    && $extra['authentication_context_type'] === 'APP_AUTHENTICATION_DEFAULT';
            })
            ->andReturn(['redirect_url' => 'https://example.com/auth']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/cp/login');
        $response->assertStatus(200);
    }

    public function test_it_overrides_auth_context_type_from_query(): void
    {
        Config::set('corppass-login.client_id', 'test-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/callback');
        Config::set('corppass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('corppass-login.domain', 'https://example.com');
        Config::set('corppass-login.available_scopes', ['openid']);
        Config::set('corppass-login.login_scopes', ['openid']);
        Config::set('corppass-login.authentication_context_type', 'APP_AUTHENTICATION_DEFAULT');

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function ($config, $scopes, $extra) {
                return isset($extra['authentication_context_type'])
                    && $extra['authentication_context_type'] === 'CUSTOM_CONTEXT';
            })
            ->andReturn(['redirect_url' => 'https://example.com/auth']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/cp/login?authentication_context_type=CUSTOM_CONTEXT');
        $response->assertStatus(200);
    }
}
