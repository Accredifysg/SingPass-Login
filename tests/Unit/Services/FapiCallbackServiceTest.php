<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\FapiCallbackResult;
use Accredifysg\SingPassLogin\DTOs\FapiSessionContext;
use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Accredifysg\SingPassLogin\Exceptions\AuthenticationErrorException;
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetUserInfoServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\JwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\JwtServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\TokenExchangeServiceInterface;
use Accredifysg\SingPassLogin\Services\FapiCallbackService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\Request;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Mockery;
use Mockery\MockInterface;

class FapiCallbackServiceTest extends TestCase
{
    private JWK $dpopKey;

    private OpenIdDiscoveryServiceInterface&MockInterface $discoveryMock;

    private TokenExchangeServiceInterface&MockInterface $tokenMock;

    private JwtServiceInterface&MockInterface $jwtMock;

    private JwksServiceInterface&MockInterface $jwksMock;

    private GetUserInfoServiceInterface&MockInterface $userInfoMock;

    private DPoPServiceInterface&MockInterface $dpopMock;

    private FapiCallbackService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dpopKey = JWKFactory::createECKey('P-256');

        /** @var OpenIdDiscoveryServiceInterface&MockInterface $discoveryMock */
        $discoveryMock = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $this->discoveryMock = $discoveryMock;

        /** @var TokenExchangeServiceInterface&MockInterface $tokenMock */
        $tokenMock = Mockery::mock(TokenExchangeServiceInterface::class);
        $this->tokenMock = $tokenMock;

        /** @var JwtServiceInterface&MockInterface $jwtMock */
        $jwtMock = Mockery::mock(JwtServiceInterface::class);
        $this->jwtMock = $jwtMock;

        /** @var JwksServiceInterface&MockInterface $jwksMock */
        $jwksMock = Mockery::mock(JwksServiceInterface::class);
        $this->jwksMock = $jwksMock;

        /** @var GetUserInfoServiceInterface&MockInterface $userInfoMock */
        $userInfoMock = Mockery::mock(GetUserInfoServiceInterface::class);
        $this->userInfoMock = $userInfoMock;

        /** @var DPoPServiceInterface&MockInterface $dpopMock */
        $dpopMock = Mockery::mock(DPoPServiceInterface::class);
        $this->dpopMock = $dpopMock;

        $this->service = new FapiCallbackService(
            $this->discoveryMock,
            $this->tokenMock,
            $this->jwtMock,
            $this->jwksMock,
            $this->userInfoMock,
            $this->dpopMock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function buildConfig(): ProviderConfig
    {
        return new ProviderConfig(
            discoveryEndpoint: 'https://example.com/discovery',
            clientId: 'test-client-id',
            redirectUri: 'https://example.com/callback',
            domain: 'https://example.com',
            cacheKey: 'openId:test',
            availableScopes: ['openid', 'name'],
            loginScopes: ['openid', 'user.identity', 'name', 'email', 'mobileno'],
        );
    }

    private function buildSession(): FapiSessionContext
    {
        return new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $this->dpopKey,
            clientId: 'test-client-id',
            redirectUri: 'https://example.com/callback',
        );
    }

    private function storeSessionData(string $state = 'test-state'): void
    {
        session()->put("auth_state_{$state}", true);
        session()->put("code_verifier_{$state}", 'test-verifier');
        session()->put("auth_client_id_{$state}", 'test-client-id');
        session()->put("auth_redirect_uri_{$state}", 'https://example.com/callback');
        $this->dpopMock->shouldReceive('retrieveKeyForState')
            ->with($state)
            ->andReturn($this->dpopKey);
    }

    public function test_validate_and_retrieve_session_success(): void
    {
        $this->storeSessionData();

        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $session = $this->service->validateAndRetrieveSession($request);

        $this->assertInstanceOf(FapiSessionContext::class, $session);
        $this->assertEquals('test-code', $session->code);
        $this->assertEquals('test-state', $session->state);
        $this->assertEquals('test-verifier', $session->codeVerifier);
        $this->assertEquals('test-client-id', $session->clientId);
    }

    public function test_validate_throws_on_error_response(): void
    {
        $request = new Request([
            'error' => 'access_denied',
            'error_description' => 'User denied',
        ]);

        $this->expectException(AuthenticationErrorException::class);

        $this->service->validateAndRetrieveSession($request);
    }

    public function test_validate_throws_on_missing_code(): void
    {
        $request = new Request(['state' => 'test-state']);

        $this->expectException(AuthFlowException::class);

        $this->service->validateAndRetrieveSession($request);
    }

    public function test_validate_throws_on_invalid_csrf_state(): void
    {
        $request = new Request(['code' => 'test-code', 'state' => 'forged-state']);

        $this->expectException(AuthFlowException::class);

        $this->service->validateAndRetrieveSession($request);
    }

    public function test_validate_throws_on_missing_session_data(): void
    {
        session()->put('auth_state_test-state', true);

        $this->dpopMock->shouldReceive('retrieveKeyForState')
            ->with('test-state')
            ->andReturn(null);

        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $this->expectException(AuthFlowException::class);

        $this->service->validateAndRetrieveSession($request);
    }

    public function test_process_callback_login_path(): void
    {
        $session = $this->buildSession();
        $config = $this->buildConfig();

        $this->discoveryMock->shouldReceive('cacheOpenIdDiscovery')->once();

        $this->tokenMock->shouldReceive('getToken')
            ->once()
            ->with('test-code', 'test-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback', 'openId:test')
            ->andReturn(new TokenResponseDto('jwe_token'));

        $this->jwtMock->shouldReceive('jweDecrypt')
            ->once()
            ->with('jwe_token')
            ->andReturn('jwt_token');

        $jwks = JWKSet::createFromKeyData(['keys' => [['kty' => 'RSA', 'kid' => '1', 'use' => 'sig', 'n' => '...', 'e' => 'AQAB']]]);
        $this->jwksMock->shouldReceive('getJwks')
            ->once()
            ->with('openId:test')
            ->andReturn($jwks);

        $payload = [
            'sub' => 'test-uuid',
            'sub_attributes' => ['identity_number' => 'S1234567A'],
        ];
        $this->jwtMock->shouldReceive('jwtDecode')
            ->once()
            ->with('jwt_token', $jwks)
            ->andReturn($payload);

        $this->jwtMock->shouldReceive('verifyPayload')
            ->once()
            ->with($payload, 'test-client-id', 'https://example.com');

        $result = $this->service->processCallback($session, $config);

        $this->assertInstanceOf(FapiCallbackResult::class, $result);
        $this->assertEquals($payload, $result->idTokenPayload);
        $this->assertNull($result->userInfoData);
    }

    public function test_process_callback_myinfo_path(): void
    {
        $session = $this->buildSession();
        $config = $this->buildConfig();

        $this->discoveryMock->shouldReceive('cacheOpenIdDiscovery')->once();

        $this->tokenMock->shouldReceive('getToken')
            ->once()
            ->andReturn(new TokenResponseDto('jwe_token', 'access-token'));

        $myInfoData = ['uinfin' => ['value' => 'S1234567A']];

        $this->userInfoMock->shouldReceive('shouldCallUserInfo')
            ->once()
            ->with('access-token', $config->loginScopes)
            ->andReturn(true);

        $this->userInfoMock->shouldReceive('getUserInfo')
            ->once()
            ->with('access-token', $this->dpopKey, 'openId:test')
            ->andReturn($myInfoData);

        $result = $this->service->processCallback($session, $config);

        $this->assertInstanceOf(FapiCallbackResult::class, $result);
        $this->assertNull($result->idTokenPayload);
        $this->assertEquals($myInfoData, $result->userInfoData);
    }

    public function test_cleanup_session(): void
    {
        $state = 'test-state';
        session()->put("code_verifier_{$state}", 'verifier');
        session()->put("auth_client_id_{$state}", 'client');
        session()->put("auth_redirect_uri_{$state}", 'uri');
        session()->put("auth_state_{$state}", true);

        $this->dpopMock->shouldReceive('clearKeyForState')->once()->with($state);

        $this->service->cleanupSession($state);

        $this->assertNull(session()->get("code_verifier_{$state}"));
        $this->assertNull(session()->get("auth_client_id_{$state}"));
        $this->assertNull(session()->get("auth_redirect_uri_{$state}"));
        $this->assertNull(session()->get("auth_state_{$state}"));
    }
}
