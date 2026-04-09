<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers;

use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\PushedAuthorizationRequestServiceInterface;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Mockery;

class GetAuthenticationEndpointControllerTest extends TestCase
{
    use RefreshDatabase;

    private JWK $dpopKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dpopKey = JWKFactory::createECKey('P-256');

        // Mock DPoP service
        $dpopMock = Mockery::mock(DPoPServiceInterface::class);
        $dpopMock->shouldReceive('generateKeyPair')->andReturn($this->dpopKey);
        $dpopMock->shouldReceive('generateProofJwt')->andReturn('mock-dpop-proof');
        $dpopMock->shouldReceive('storeKeyForState');
        app()->instance(DPoPServiceInterface::class, $dpopMock);

        // Mock PAR service
        $parMock = Mockery::mock(PushedAuthorizationRequestServiceInterface::class);
        $parMock->shouldReceive('sendRequest')
            ->andReturn('urn:ietf:params:oauth:request_uri:test-request-uri');
        app()->instance(PushedAuthorizationRequestServiceInterface::class, $parMock);

        // Mock SingPassJwtService static methods
        $singPassJwtServiceMock = Mockery::mock('alias:'.SingPassJwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $this->dpopKey,
            'generateClientAssertion' => 'mock-client-assertion',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function fullDiscoveryResponse(): string
    {
        return json_encode([
            'issuer' => 'https://example.com',
            'authorization_endpoint' => 'https://example.com/auth',
            'token_endpoint' => 'https://example.com/token',
            'userinfo_endpoint' => 'https://example.com/userinfo',
            'jwks_uri' => 'https://example.com/jwks',
            'pushed_authorization_request_endpoint' => 'https://example.com/par',
        ]) ?: '{}';
    }

    public function test_it_returns_a_valid_singpass_login_url(): void
    {
        $mockResponse = $this->fullDiscoveryResponse();

        Http::fake([
            'https://example.com/discovery' => Http::response($mockResponse, 200),
        ]);

        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.redirect_uri', 'http://redirect.uri');
        Config::set('singpass-login.client_id', 'test-client-id');

        $response = $this->getJson('/sp/login');

        $response->assertStatus(200)
            ->assertJsonStructure(['redirect_url']);

        $redirectUrl = $response->json('redirect_url');

        parse_str(parse_url($redirectUrl, PHP_URL_QUERY) ?: '', $queryParams);

        $this->assertEquals('test-client-id', $queryParams['client_id']);
        $this->assertEquals('urn:ietf:params:oauth:request_uri:test-request-uri', $queryParams['request_uri']);
        $this->assertCount(2, $queryParams);
    }

    public function test_it_accepts_login_scopes_and_uses_login_client(): void
    {
        $mockResponse = $this->fullDiscoveryResponse();

        Http::fake([
            'https://example.com/discovery' => Http::response($mockResponse, 200),
        ]);

        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.redirect_uri', 'http://redirect.uri');
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.myinfo_redirect_uri', 'http://myinfo-redirect.uri');
        Config::set('singpass-login.myinfo_client_id', 'myinfo-client-id');
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email', 'mobileno', 'user.identity']);
        Config::set('singpass-login.login_scopes', ['openid', 'user.identity', 'name', 'email', 'mobileno']);

        $response = $this->getJson('/sp/login?scopes=openid,name,email');

        $response->assertStatus(200);

        $redirectUrl = $response->json('redirect_url');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY) ?: '', $queryParams);

        $this->assertEquals('test-client-id', $queryParams['client_id']);
        $this->assertArrayHasKey('request_uri', $queryParams);
    }

    public function test_it_uses_myinfo_client_for_myinfo_scopes(): void
    {
        $mockResponse = $this->fullDiscoveryResponse();

        Http::fake([
            'https://example.com/discovery' => Http::response($mockResponse, 200),
        ]);

        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.redirect_uri', 'http://redirect.uri');
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.myinfo_redirect_uri', 'http://myinfo-redirect.uri');
        Config::set('singpass-login.myinfo_client_id', 'myinfo-client-id');
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email', 'uinfin', 'regadd']);
        Config::set('singpass-login.login_scopes', ['openid', 'user.identity', 'name', 'email', 'mobileno']);

        $response = $this->getJson('/sp/login?scopes=openid,uinfin,regadd');

        $response->assertStatus(200);

        $redirectUrl = $response->json('redirect_url');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY) ?: '', $queryParams);

        $this->assertEquals('myinfo-client-id', $queryParams['client_id']);
        $this->assertArrayHasKey('request_uri', $queryParams);
    }

    public function test_it_throws_exception_for_invalid_scopes(): void
    {
        $this->withoutExceptionHandling();

        $mockResponse = $this->fullDiscoveryResponse();

        Http::fake([
            'https://example.com/discovery' => Http::response($mockResponse, 200),
        ]);

        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.redirect_uri', 'http://redirect.uri');
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.myinfo_redirect_uri', 'http://myinfo-redirect.uri');
        Config::set('singpass-login.myinfo_client_id', 'myinfo-client-id');
        Config::set('singpass-login.available_scopes', ['openid', 'name', 'email']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid scope requested: 'invalid_scope'");

        $this->getJson('/sp/login?scopes=openid,name,invalid_scope');
    }

    public function test_it_stores_dpop_key_and_code_verifier_in_session(): void
    {
        $mockResponse = $this->fullDiscoveryResponse();

        Http::fake([
            'https://example.com/discovery' => Http::response($mockResponse, 200),
        ]);

        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.redirect_uri', 'http://redirect.uri');
        Config::set('singpass-login.client_id', 'test-client-id');

        $response = $this->getJson('/sp/login');

        $response->assertStatus(200);
    }
}
