<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers\MyInfo;

use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
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
        Config::set('myinfo.client_id', 'myinfo-client-id');
        Config::set('myinfo.redirect_uri', 'https://example.com/myinfo-callback');
        Config::set('myinfo.discovery_endpoint', 'https://example.com/discovery');
        Config::set('myinfo.domain', 'https://example.com');
        Config::set('myinfo.available_scopes', ['openid', 'uinfin']);
        Config::set('myinfo.login_scopes', ['openid']);

        $fapiAuthMock = Mockery::mock(FapiAuthenticationService::class);
        $fapiAuthMock->shouldReceive('initiateAuth')
            ->once()
            ->withArgs(function (ProviderConfig $config, mixed $scopes): bool {
                return $config->clientId === 'myinfo-client-id';
            })
            ->andReturn(['redirect_url' => 'https://example.com/auth?client_id=myinfo&request_uri=urn:test']);

        app()->instance(FapiAuthenticationService::class, $fapiAuthMock);

        $response = $this->getJson('/ndi/mi/initiate');

        $response->assertStatus(200)
            ->assertJsonStructure(['redirect_url']);
    }
}
