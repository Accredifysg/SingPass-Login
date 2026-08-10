<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\PushedAuthorizationRequestServiceInterface;
use Accredifysg\SingPassLogin\Services\CodeChallengeVerifierService;
use Accredifysg\SingPassLogin\Services\FapiAuthenticationService;
use Accredifysg\SingPassLogin\Services\JwtService;
use Accredifysg\SingPassLogin\Services\ScopeValidationService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Mockery;

class FapiAuthenticationServiceTest extends TestCase
{
    private JWK $dpopKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dpopKey = JWKFactory::createECKey('P-256');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createService(
        ?OpenIdDiscoveryServiceInterface $discovery = null,
        ?ScopeValidationService $scope = null,
        ?CodeChallengeVerifierService $codeChallenge = null,
        ?DPoPServiceInterface $dpop = null,
        ?PushedAuthorizationRequestServiceInterface $par = null,
    ): FapiAuthenticationService {
        return new FapiAuthenticationService(
            $discovery ?? Mockery::mock(OpenIdDiscoveryServiceInterface::class),
            $scope ?? new ScopeValidationService,
            $codeChallenge ?? new CodeChallengeVerifierService,
            $dpop ?? Mockery::mock(DPoPServiceInterface::class),
            $par ?? Mockery::mock(PushedAuthorizationRequestServiceInterface::class),
        );
    }

    private function buildConfig(): ProviderConfig
    {
        return new ProviderConfig(
            discoveryEndpoint: 'https://example.com/discovery',
            clientId: 'test-client-id',
            redirectUri: 'https://example.com/callback',
            domain: 'https://example.com',
            cacheKey: 'openId:test',
            availableScopes: ['openid', 'name', 'email'],
            loginScopes: ['openid', 'user.identity', 'name', 'email', 'mobileno'],
        );
    }

    private function seedCache(string $cacheKey = 'openId:test'): void
    {
        Cache::put($cacheKey, (new OpenIdConfigurationDto(
            issuer: 'https://example.com',
            authorizationEndpoint: 'https://example.com/auth',
            tokenEndpoint: 'https://example.com/token',
            userinfoEndpoint: 'https://example.com/userinfo',
            jwksUri: 'https://example.com/jwks',
            pushedAuthorizationRequestEndpoint: 'https://example.com/par',
        ))->toArray());
    }

    public function test_initiate_auth_returns_redirect_url(): void
    {
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $discoveryMock->shouldReceive('cacheOpenIdDiscovery')
            ->once()
            ->with('https://example.com/discovery', 'openId:test');

        $dpopMock = Mockery::mock(DPoPServiceInterface::class);
        $dpopMock->shouldReceive('generateKeyPair')->once()->andReturn($this->dpopKey);
        $dpopMock->shouldReceive('generateProofJwt')->once()->andReturn('mock-dpop-proof');
        $dpopMock->shouldReceive('storeKeyForState')->once();

        $parMock = Mockery::mock(PushedAuthorizationRequestServiceInterface::class);
        $parMock->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (array $params, string $dpopProof, string $cacheKey) {
                return $cacheKey === 'openId:test';
            })
            ->andReturn('urn:ietf:params:oauth:request_uri:test-uri');

        $jwtMock = Mockery::mock('alias:'.JwtService::class);
        $jwtMock->allows([
            'getSigningJwk' => $this->dpopKey,
            'generateClientAssertion' => 'mock-client-assertion',
        ]);

        $this->seedCache();

        $service = $this->createService(
            discovery: $discoveryMock,
            dpop: $dpopMock,
            par: $parMock,
        );

        $result = $service->initiateAuth($this->buildConfig(), 'openid,name');

        $this->assertArrayHasKey('redirect_url', $result);
        $this->assertStringContainsString('https://example.com/auth?', $result['redirect_url']);
        $this->assertStringContainsString('client_id=test-client-id', $result['redirect_url']);
        $this->assertStringContainsString('request_uri=', $result['redirect_url']);
    }

    public function test_initiate_auth_stores_session_data(): void
    {
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $discoveryMock->shouldReceive('cacheOpenIdDiscovery');

        $dpopMock = Mockery::mock(DPoPServiceInterface::class);
        $dpopMock->shouldReceive('generateKeyPair')->andReturn($this->dpopKey);
        $dpopMock->shouldReceive('generateProofJwt')->andReturn('mock-dpop-proof');
        $dpopMock->shouldReceive('storeKeyForState');

        $parMock = Mockery::mock(PushedAuthorizationRequestServiceInterface::class);
        $parMock->shouldReceive('sendRequest')->andReturn('urn:test');

        $jwtMock = Mockery::mock('alias:'.JwtService::class);
        $jwtMock->allows([
            'getSigningJwk' => $this->dpopKey,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        $this->seedCache();

        $service = $this->createService(
            discovery: $discoveryMock,
            dpop: $dpopMock,
            par: $parMock,
        );

        $service->initiateAuth($this->buildConfig(), 'openid');

        $sessionData = session()->all();

        $hasAuthState = false;
        $hasCodeVerifier = false;
        $hasClientId = false;
        $hasRedirectUri = false;

        foreach ($sessionData as $key => $value) {
            if (str_starts_with($key, 'auth_state_')) {
                $hasAuthState = true;
            }
            if (str_starts_with($key, 'code_verifier_')) {
                $hasCodeVerifier = true;
            }
            if (str_starts_with($key, 'auth_client_id_')) {
                $hasClientId = true;
                $this->assertEquals('test-client-id', $value);
            }
            if (str_starts_with($key, 'auth_redirect_uri_')) {
                $hasRedirectUri = true;
                $this->assertEquals('https://example.com/callback', $value);
            }
        }

        $this->assertTrue($hasAuthState, 'Session should contain auth_state_*');
        $this->assertTrue($hasCodeVerifier, 'Session should contain code_verifier_*');
        $this->assertTrue($hasClientId, 'Session should contain auth_client_id_*');
        $this->assertTrue($hasRedirectUri, 'Session should contain auth_redirect_uri_*');
    }

    public function test_initiate_auth_with_extra_par_params(): void
    {
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $discoveryMock->shouldReceive('cacheOpenIdDiscovery');

        $dpopMock = Mockery::mock(DPoPServiceInterface::class);
        $dpopMock->shouldReceive('generateKeyPair')->andReturn($this->dpopKey);
        $dpopMock->shouldReceive('generateProofJwt')->andReturn('mock-dpop-proof');
        $dpopMock->shouldReceive('storeKeyForState');

        $parMock = Mockery::mock(PushedAuthorizationRequestServiceInterface::class);
        $parMock->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (array $params) {
                return isset($params['authentication_context_type'])
                    && $params['authentication_context_type'] === 'APP_AUTHENTICATION_DEFAULT';
            })
            ->andReturn('urn:test');

        $jwtMock = Mockery::mock('alias:'.JwtService::class);
        $jwtMock->allows([
            'getSigningJwk' => $this->dpopKey,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        $this->seedCache();

        $service = $this->createService(
            discovery: $discoveryMock,
            dpop: $dpopMock,
            par: $parMock,
        );

        $result = $service->initiateAuth(
            $this->buildConfig(),
            'openid',
            ['authentication_context_type' => 'APP_AUTHENTICATION_DEFAULT'],
        );

        $this->assertArrayHasKey('redirect_url', $result);
    }

    public function test_initiate_auth_accepts_array_requested_scopes(): void
    {
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $discoveryMock->shouldReceive('cacheOpenIdDiscovery');

        $dpopMock = Mockery::mock(DPoPServiceInterface::class);
        $dpopMock->shouldReceive('generateKeyPair')->andReturn($this->dpopKey);
        $dpopMock->shouldReceive('generateProofJwt')->andReturn('mock-dpop-proof');
        $dpopMock->shouldReceive('storeKeyForState');

        $parMock = Mockery::mock(PushedAuthorizationRequestServiceInterface::class);
        $parMock->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (array $params): bool {
                return isset($params['scope']) && $params['scope'] === 'openid name';
            })
            ->andReturn('urn:test');

        $jwtMock = Mockery::mock('alias:'.JwtService::class);
        $jwtMock->allows([
            'getSigningJwk' => $this->dpopKey,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        $this->seedCache();

        $service = $this->createService(
            discovery: $discoveryMock,
            dpop: $dpopMock,
            par: $parMock,
        );

        $result = $service->initiateAuth($this->buildConfig(), ['openid', 'name']);

        $this->assertArrayHasKey('redirect_url', $result);
    }

    public function test_initiate_auth_throws_when_requested_scope_is_not_string(): void
    {
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $discoveryMock->shouldReceive('cacheOpenIdDiscovery');

        $this->seedCache();

        $service = $this->createService(discovery: $discoveryMock);

        $this->expectException(AuthFlowException::class);
        $this->expectExceptionMessage('Each requested scope must be a string.');

        $service->initiateAuth($this->buildConfig(), ['openid', 1]);
    }

    public function test_initiate_auth_defaults_scopes_to_openid_when_requested_scopes_invalid_type(): void
    {
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $discoveryMock->shouldReceive('cacheOpenIdDiscovery');

        $dpopMock = Mockery::mock(DPoPServiceInterface::class);
        $dpopMock->shouldReceive('generateKeyPair')->andReturn($this->dpopKey);
        $dpopMock->shouldReceive('generateProofJwt')->andReturn('mock-dpop-proof');
        $dpopMock->shouldReceive('storeKeyForState');

        $parMock = Mockery::mock(PushedAuthorizationRequestServiceInterface::class);
        $parMock->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (array $params): bool {
                return isset($params['scope']) && $params['scope'] === 'openid';
            })
            ->andReturn('urn:test');

        $jwtMock = Mockery::mock('alias:'.JwtService::class);
        $jwtMock->allows([
            'getSigningJwk' => $this->dpopKey,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        $this->seedCache();

        $service = $this->createService(
            discovery: $discoveryMock,
            dpop: $dpopMock,
            par: $parMock,
        );

        $result = $service->initiateAuth($this->buildConfig(), new \stdClass);

        $this->assertArrayHasKey('redirect_url', $result);
    }

    public function test_initiate_auth_coerces_extra_par_scalar_types_to_strings(): void
    {
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $discoveryMock->shouldReceive('cacheOpenIdDiscovery');

        $dpopMock = Mockery::mock(DPoPServiceInterface::class);
        $dpopMock->shouldReceive('generateKeyPair')->andReturn($this->dpopKey);
        $dpopMock->shouldReceive('generateProofJwt')->andReturn('mock-dpop-proof');
        $dpopMock->shouldReceive('storeKeyForState');

        $parMock = Mockery::mock(PushedAuthorizationRequestServiceInterface::class);
        $parMock->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (array $params): bool {
                return $params['port'] === '443'
                    && $params['ratio'] === '1.5'
                    && $params['flag'] === 'true';
            })
            ->andReturn('urn:test');

        $jwtMock = Mockery::mock('alias:'.JwtService::class);
        $jwtMock->allows([
            'getSigningJwk' => $this->dpopKey,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        $this->seedCache();

        $service = $this->createService(
            discovery: $discoveryMock,
            dpop: $dpopMock,
            par: $parMock,
        );

        $result = $service->initiateAuth($this->buildConfig(), 'openid', [
            'port' => 443,
            'ratio' => 1.5,
            'flag' => true,
        ]);

        $this->assertArrayHasKey('redirect_url', $result);
    }

    public function test_initiate_auth_throws_when_extra_par_value_is_not_scalar_compatible(): void
    {
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $discoveryMock->shouldReceive('cacheOpenIdDiscovery');

        $this->seedCache();

        $service = $this->createService(discovery: $discoveryMock);

        $this->expectException(AuthFlowException::class);
        $this->expectExceptionMessage('PAR parameters must be scalar string-compatible values.');

        $service->initiateAuth($this->buildConfig(), 'openid', ['x' => []]);
    }

    public function test_initiate_auth_throws_when_extra_par_key_is_not_string(): void
    {
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $discoveryMock->shouldReceive('cacheOpenIdDiscovery');

        $this->seedCache();

        $service = $this->createService(discovery: $discoveryMock);

        $this->expectException(AuthFlowException::class);
        $this->expectExceptionMessage('PAR parameter names must be strings.');

        $service->initiateAuth($this->buildConfig(), 'openid', [0 => 'value']);
    }

    public function test_initiate_auth_throws_when_openid_config_missing_from_cache(): void
    {
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $discoveryMock->shouldReceive('cacheOpenIdDiscovery');

        Cache::forget('openId:test');

        $dpopMock = Mockery::mock(DPoPServiceInterface::class);
        $dpopMock->shouldReceive('generateKeyPair')->once()->andReturn($this->dpopKey);

        $service = $this->createService(
            discovery: $discoveryMock,
            dpop: $dpopMock,
        );

        $this->expectException(AuthFlowException::class);
        $this->expectExceptionMessage('OpenID configuration not found in cache');

        $service->initiateAuth($this->buildConfig(), 'openid');
    }
}
