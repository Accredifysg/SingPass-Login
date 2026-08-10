<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Accredifysg\SingPassLogin\Exceptions\TokenExchangeException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Services\JwtService;
use Accredifysg\SingPassLogin\Services\TokenExchangeService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Mockery;
use Mockery\MockInterface;

class TokenExchangeServiceTest extends TestCase
{
    private JWK $dpopKey;

    private DPoPServiceInterface&MockInterface $dpopServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dpopKey = JWKFactory::createECKey('P-256');

        /** @var DPoPServiceInterface&MockInterface $dpopServiceMock */
        $dpopServiceMock = Mockery::mock(DPoPServiceInterface::class);
        $this->dpopServiceMock = $dpopServiceMock;
        $this->dpopServiceMock->shouldReceive('generateProofJwt')
            ->andReturn('mock-dpop-proof-jwt');

        Cache::put('openId', (new OpenIdConfigurationDto(
            issuer: 'https://example.com',
            authorizationEndpoint: 'https://example.com/auth',
            tokenEndpoint: 'https://example.com/token',
            userinfoEndpoint: 'https://example.com/userinfo',
            jwksUri: 'https://example.com/jwks',
            pushedAuthorizationRequestEndpoint: 'https://example.com/par',
        ))->toArray());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_token_success(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => $mockClientAssertion,
        ]);

        $mockResponse = [
            'id_token' => 'mock-id-token',
            'access_token' => 'mock-access-token',
            'token_type' => 'DPoP',
        ];

        Http::fake([
            'https://example.com/token' => Http::response($mockResponse, 200),
        ]);

        $service = new TokenExchangeService($this->dpopServiceMock);
        $tokenResponse = $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');

        $this->assertInstanceOf(TokenResponseDto::class, $tokenResponse);
        $this->assertEquals('mock-id-token', $tokenResponse->idToken);
        $this->assertEquals('mock-access-token', $tokenResponse->accessToken);
        $this->assertTrue($tokenResponse->hasAccessToken());
    }

    public function test_get_token_with_myinfo_credentials(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => $mockClientAssertion,
        ]);

        $mockResponse = [
            'id_token' => 'mock-id-token',
            'access_token' => 'mock-access-token',
            'token_type' => 'DPoP',
        ];

        Http::fake([
            'https://example.com/token' => Http::response($mockResponse, 200),
        ]);

        $service = new TokenExchangeService($this->dpopServiceMock);
        $tokenResponse = $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'myinfo-client-id', 'https://example.com/myinfo-callback', 'openId');

        $this->assertInstanceOf(TokenResponseDto::class, $tokenResponse);
        $this->assertEquals('mock-id-token', $tokenResponse->idToken);
        $this->assertEquals('mock-access-token', $tokenResponse->accessToken);
        $this->assertTrue($tokenResponse->hasAccessToken());
    }

    public function test_get_token_without_access_token(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => $mockClientAssertion,
        ]);

        $mockResponse = [
            'id_token' => 'mock-id-token',
        ];

        Http::fake([
            'https://example.com/token' => Http::response($mockResponse, 200),
        ]);

        $service = new TokenExchangeService($this->dpopServiceMock);
        $tokenResponse = $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');

        $this->assertInstanceOf(TokenResponseDto::class, $tokenResponse);
        $this->assertEquals('mock-id-token', $tokenResponse->idToken);
        $this->assertNull($tokenResponse->accessToken);
        $this->assertFalse($tokenResponse->hasAccessToken());
    }

    public function test_get_token_unparseable_response(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        Http::fake([
            'https://example.com/token' => Http::response('not-json', 200),
        ]);

        $service = new TokenExchangeService($this->dpopServiceMock);

        try {
            $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');
            $this->fail('Expected TokenExchangeException');
        } catch (TokenExchangeException $e) {
            $this->assertSame(502, $e->getStatusCode());
            $this->assertSame('Failed to parse token endpoint response', $e->getMessage());
        }
    }

    public function test_get_token_unparseable_response_preserves_upstream_error_status(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        Http::fake([
            'https://example.com/token' => Http::response('<html>error</html>', 400),
        ]);

        $service = new TokenExchangeService($this->dpopServiceMock);

        try {
            $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');
            $this->fail('Expected TokenExchangeException');
        } catch (TokenExchangeException $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('Failed to parse token endpoint response', $e->getMessage());
        }
    }

    public function test_get_token_oauth_error_response(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        Http::fake([
            'https://example.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'The authorization code has expired.',
            ], 400),
        ]);

        $this->expectException(TokenExchangeException::class);
        $this->expectExceptionMessage('invalid_grant: The authorization code has expired.');

        $service = new TokenExchangeService($this->dpopServiceMock);
        $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');
    }

    public function test_get_token_server_error(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        Http::fake([
            'https://example.com/token' => Http::response(['message' => 'Internal Server Error'], 500),
        ]);

        $this->expectException(TokenExchangeException::class);
        $this->expectExceptionMessage('server_error: Token exchange request failed');

        $service = new TokenExchangeService($this->dpopServiceMock);
        $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');
    }

    public function test_get_token_missing_id_token(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        Http::fake([
            'https://example.com/token' => Http::response([
                'access_token' => 'mock-access-token',
                'token_type' => 'DPoP',
            ], 200),
        ]);

        $this->expectException(TokenExchangeException::class);
        $this->expectExceptionMessage('Token response missing id_token');

        $service = new TokenExchangeService($this->dpopServiceMock);
        $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');
    }

    public function test_get_token_throws_when_openid_config_missing_from_cache(): void
    {
        Cache::forget('openId');

        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        $this->expectException(TokenExchangeException::class);
        $this->expectExceptionMessage('OpenID configuration not found in cache');

        $service = new TokenExchangeService($this->dpopServiceMock);
        $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');
    }

    public function test_get_token_rejects_json_array_body(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        Http::fake([
            'https://example.com/token' => Http::response('[]', 200),
        ]);

        $this->expectException(TokenExchangeException::class);
        $this->expectExceptionMessage('Token endpoint JSON must be an object');

        $service = new TokenExchangeService($this->dpopServiceMock);
        $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');
    }

    public function test_get_token_rejects_non_string_id_token(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        Http::fake([
            'https://example.com/token' => Http::response([
                'id_token' => ['not' => 'a string'],
            ], 200),
        ]);

        $this->expectException(TokenExchangeException::class);
        $this->expectExceptionMessage('Token response id_token must be a string');

        $service = new TokenExchangeService($this->dpopServiceMock);
        $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');
    }

    public function test_get_token_oauth_error_uses_defaults_when_error_fields_not_strings(): void
    {
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];

        $singPassJwtServiceMock = Mockery::mock('alias:'.JwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => 'mock-assertion',
        ]);

        Http::fake([
            'https://example.com/token' => Http::response([
                'error' => 99,
                'error_description' => ['nested' => 'x'],
            ], 400),
        ]);

        $this->expectException(TokenExchangeException::class);
        $this->expectExceptionMessage('server_error: Token exchange request failed');

        $service = new TokenExchangeService($this->dpopServiceMock);
        $service->getToken('mock-code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId');
    }
}
