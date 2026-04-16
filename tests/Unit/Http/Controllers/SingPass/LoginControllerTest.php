<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers\SingPass;

use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
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
        Config::set('singpass-login.login_scopes', ['openid']);
        Config::set('singpass-login.authentication_context_type', 'APP_AUTHENTICATION_DEFAULT');
        Config::set('singpass-login.authentication_context_message', null);

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function (ProviderConfig $config, mixed $scopes, array $extra): bool {
                return isset($extra['authentication_context_type'])
                    && $extra['authentication_context_type'] === 'APP_AUTHENTICATION_DEFAULT';
            })
            ->andReturn(['redirect_url' => 'https://example.com/auth']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/sp/login');
        $response->assertStatus(200);
    }

    public function test_it_passes_query_auth_context_over_config(): void
    {
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.domain', 'https://example.com');
        Config::set('singpass-login.login_scopes', ['openid']);
        Config::set('singpass-login.authentication_context_type', 'CONFIG_TYPE');
        Config::set('singpass-login.authentication_context_message', 'Config message');

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function (ProviderConfig $config, mixed $scopes, array $extra): bool {
                return ($extra['authentication_context_type'] ?? null) === 'QUERY_TYPE'
                    && ($extra['authentication_context_message'] ?? null) === 'Query message';
            })
            ->andReturn(['redirect_url' => 'https://example.com/auth']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/sp/login?authentication_context_type=QUERY_TYPE&authentication_context_message=Query%20message');
        $response->assertStatus(200);
    }

    public function test_it_passes_authentication_context_message_from_config_when_type_from_query(): void
    {
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.domain', 'https://example.com');
        Config::set('singpass-login.login_scopes', ['openid']);
        Config::set('singpass-login.authentication_context_type', null);
        Config::set('singpass-login.authentication_context_message', 'Only from config');

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function (ProviderConfig $config, mixed $scopes, array $extra): bool {
                return ($extra['authentication_context_type'] ?? null) === 'FROM_QUERY'
                    && ($extra['authentication_context_message'] ?? null) === 'Only from config';
            })
            ->andReturn(['redirect_url' => 'https://example.com/auth']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/sp/login?authentication_context_type=FROM_QUERY');
        $response->assertStatus(200);
    }

    public function test_it_passes_scopes_query_to_initiate_auth(): void
    {
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.domain', 'https://example.com');
        Config::set('singpass-login.login_scopes', ['openid']);

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function (ProviderConfig $config, mixed $scopes, array $extra): bool {
                return $scopes === 'openid,name';
            })
            ->andReturn(['redirect_url' => 'https://example.com/auth']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/sp/login?scopes=openid%2Cname');
        $response->assertStatus(200);
    }
}
