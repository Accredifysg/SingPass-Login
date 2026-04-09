<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Accredifysg\SingPassLogin\Exceptions\SingPassTokenException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Services\GetSingPassTokenService;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Mockery;
use Mockery\MockInterface;

class GetSingPassTokenServiceTest extends TestCase
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

        Cache::put('openId', new OpenIdConfigurationDto(
            issuer: 'https://example.com',
            authorizationEndpoint: 'https://example.com/auth',
            tokenEndpoint: 'https://example.com/token',
            userinfoEndpoint: 'https://example.com/userinfo',
            jwksUri: 'https://example.com/jwks',
            pushedAuthorizationRequestEndpoint: 'https://example.com/par',
        ));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_token_success(): void
    {
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');

        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.SingPassJwtService::class);
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

        // Call the method with LOGIN- state (regular auth flow)
        $service = new GetSingPassTokenService($this->dpopServiceMock);
        $tokenResponse = $service->getToken('mock-code', 'test-code-verifier', 'LOGIN-test-state', $this->dpopKey);

        $this->assertInstanceOf(TokenResponseDto::class, $tokenResponse);
        $this->assertEquals('mock-id-token', $tokenResponse->idToken);
        $this->assertEquals('mock-access-token', $tokenResponse->accessToken);
        $this->assertTrue($tokenResponse->hasAccessToken());
    }

    public function test_get_token_with_myinfo_state(): void
    {
        // Mock configuration values for MyInfo
        Config::set('singpass-login.myinfo_client_id', 'myinfo-client-id');
        Config::set('singpass-login.myinfo_redirect_uri', 'https://example.com/myinfo-callback');

        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.SingPassJwtService::class);
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

        // Call the method with MYINFO- state prefix
        $service = new GetSingPassTokenService($this->dpopServiceMock);
        $tokenResponse = $service->getToken('mock-code', 'test-code-verifier', 'MYINFO-test-state', $this->dpopKey);

        $this->assertInstanceOf(TokenResponseDto::class, $tokenResponse);
        $this->assertEquals('mock-id-token', $tokenResponse->idToken);
        $this->assertEquals('mock-access-token', $tokenResponse->accessToken);
        $this->assertTrue($tokenResponse->hasAccessToken());
    }

    public function test_get_token_without_access_token(): void
    {
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');

        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.SingPassJwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => $mockClientAssertion,
        ]);

        // Mock the HTTP response without access_token
        $mockResponse = [
            'id_token' => 'mock-id-token',
        ];

        Http::fake([
            'https://example.com/token' => Http::response($mockResponse, 200),
        ]);

        $service = new GetSingPassTokenService($this->dpopServiceMock);
        $tokenResponse = $service->getToken('mock-code', 'test-code-verifier', 'LOGIN-test-state', $this->dpopKey);

        $this->assertInstanceOf(TokenResponseDto::class, $tokenResponse);
        $this->assertEquals('mock-id-token', $tokenResponse->idToken);
        $this->assertNull($tokenResponse->accessToken);
        $this->assertFalse($tokenResponse->hasAccessToken());
    }

    public function test_get_token_exception(): void
    {
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');

        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.SingPassJwtService::class);
        $singPassJwtServiceMock->allows([
            'getSigningJwk' => $mockJwk,
            'generateClientAssertion' => $mockClientAssertion,
        ]);

        Http::fake([
            'https://example.com/token' => Http::response(null, 500),
        ]);

        $this->expectException(SingPassTokenException::class);

        $service = new GetSingPassTokenService($this->dpopServiceMock);
        $service->getToken('mock-code', 'test-code-verifier', 'LOGIN-test-state', $this->dpopKey);
    }
}
