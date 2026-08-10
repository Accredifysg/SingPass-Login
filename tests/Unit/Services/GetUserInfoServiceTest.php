<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\JwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\JwtServiceInterface;
use Accredifysg\SingPassLogin\Services\GetUserInfoService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Mockery;
use Mockery\MockInterface;

class GetUserInfoServiceTest extends TestCase
{
    private JwtServiceInterface&MockInterface $singPassJwtServiceMock;

    private JwksServiceInterface&MockInterface $getSingPassJwksServiceMock;

    private DPoPServiceInterface&MockInterface $dpopServiceMock;

    private GetUserInfoService $service;

    private JWK $dpopKey;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var JwtServiceInterface&MockInterface $singPassJwtServiceMock */
        $singPassJwtServiceMock = Mockery::mock(JwtServiceInterface::class);
        $this->singPassJwtServiceMock = $singPassJwtServiceMock;

        /** @var JwksServiceInterface&MockInterface $getSingPassJwksServiceMock */
        $getSingPassJwksServiceMock = Mockery::mock(JwksServiceInterface::class);
        $this->getSingPassJwksServiceMock = $getSingPassJwksServiceMock;

        /** @var DPoPServiceInterface&MockInterface $dpopServiceMock */
        $dpopServiceMock = Mockery::mock(DPoPServiceInterface::class);
        $this->dpopServiceMock = $dpopServiceMock;
        $this->dpopServiceMock->shouldReceive('computeAccessTokenHash')->andReturn('mock-ath');
        $this->dpopServiceMock->shouldReceive('generateProofJwt')->andReturn('mock-dpop-proof-jwt');

        $this->service = new GetUserInfoService(
            $this->singPassJwtServiceMock,
            $this->getSingPassJwksServiceMock,
            $this->dpopServiceMock
        );

        $this->dpopKey = JWKFactory::createECKey('P-256');

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

    // ========== shouldCallUserInfo() Tests ==========

    public function test_should_call_user_info_returns_false_for_openid_only_scope(): void
    {
        $accessToken = $this->createAccessTokenWithScopes(['openid']);

        $loginScopes = ['openid', 'user.identity', 'name', 'email', 'mobileno'];
        $result = $this->service->shouldCallUserInfo($accessToken, $loginScopes);

        $this->assertFalse($result);
    }

    public function test_should_call_user_info_returns_false_for_login_scopes_only(): void
    {
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'user.identity', 'name', 'email', 'mobileno']);

        $loginScopes = ['openid', 'user.identity', 'name', 'email', 'mobileno'];
        $result = $this->service->shouldCallUserInfo($accessToken, $loginScopes);

        $this->assertFalse($result);
    }

    public function test_should_call_user_info_returns_false_for_openid_and_user_identity(): void
    {
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'user.identity']);

        $loginScopes = ['openid', 'user.identity', 'name', 'email', 'mobileno'];
        $result = $this->service->shouldCallUserInfo($accessToken, $loginScopes);

        $this->assertFalse($result);
    }

    public function test_should_call_user_info_returns_true_for_myinfo_scopes(): void
    {
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'uinfin', 'regadd']);

        $loginScopes = ['openid', 'user.identity', 'name', 'email', 'mobileno'];
        $result = $this->service->shouldCallUserInfo($accessToken, $loginScopes);

        $this->assertTrue($result);
    }

    public function test_should_call_user_info_returns_true_for_mixed_login_and_myinfo_scopes(): void
    {
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'name', 'uinfin']);

        $loginScopes = ['openid', 'user.identity', 'name', 'email', 'mobileno'];
        $result = $this->service->shouldCallUserInfo($accessToken, $loginScopes);

        $this->assertTrue($result);
    }

    public function test_should_call_user_info_returns_true_for_single_myinfo_scope(): void
    {
        $accessToken = $this->createAccessTokenWithScopes(['uinfin']);

        $loginScopes = ['openid', 'user.identity', 'name', 'email', 'mobileno'];
        $result = $this->service->shouldCallUserInfo($accessToken, $loginScopes);

        $this->assertTrue($result);
    }

    public function test_should_call_user_info_throws_for_malformed_token(): void
    {
        $this->expectException(UserInfoRequestException::class);
        $this->expectExceptionMessage('Access token is not a valid JWT (expected 3 parts).');

        $this->service->shouldCallUserInfo('invalid.token', ['openid']);
    }

    public function test_should_call_user_info_throws_for_token_without_scope(): void
    {
        $accessToken = $this->createAccessTokenWithoutScopes();

        $this->expectException(UserInfoRequestException::class);
        $this->expectExceptionMessage('Access token payload does not contain a scope claim.');

        $this->service->shouldCallUserInfo($accessToken, ['openid']);
    }

    public function test_should_call_user_info_throws_for_undecodable_payload(): void
    {
        $header = base64_encode('{"alg":"RS256"}');
        $payload = '!!!invalid-base64!!!';
        $signature = base64_encode('sig');
        $accessToken = "$header.$payload.$signature";

        $this->expectException(UserInfoRequestException::class);
        $this->expectExceptionMessage('Access token payload could not be base64-decoded.');

        $this->service->shouldCallUserInfo($accessToken, ['openid']);
    }

    public function test_should_call_user_info_throws_when_scope_is_not_string(): void
    {
        $header = base64_encode('{"alg":"RS256"}');
        $payload = base64_encode((string) json_encode(['scope' => 123]));
        $signature = base64_encode('sig');
        $accessToken = "$header.$payload.$signature";

        $this->expectException(UserInfoRequestException::class);
        $this->expectExceptionMessage('Access token scope claim is not a string.');

        $this->service->shouldCallUserInfo($accessToken, ['openid']);
    }

    // ========== getUserInfo() Tests ==========

    public function test_get_user_info_throws_exception_on_http_failure(): void
    {
        Http::fake([
            'https://example.com/userinfo' => Http::response(null, 500),
        ]);

        $accessToken = $this->createAccessTokenWithScopes(['openid', 'uinfin']);

        $this->expectException(UserInfoRequestException::class);
        $this->expectExceptionMessage('UserInfo endpoint request failed with status 500');

        $this->service->getUserInfo($accessToken, $this->dpopKey, 'openId');
    }

    public function test_get_user_info_throws_exception_on_decryption_failure(): void
    {
        Http::fake([
            'https://example.com/userinfo' => Http::response('encrypted-jwe-token', 200),
        ]);

        $this->singPassJwtServiceMock
            ->shouldReceive('jweDecrypt')
            ->once()
            ->with('encrypted-jwe-token')
            ->andThrow(new Exception('Decryption failed'));

        $accessToken = $this->createAccessTokenWithScopes(['openid', 'uinfin']);

        $this->expectException(UserInfoDecryptionException::class);
        $this->expectExceptionMessage('Failed to decrypt UserInfo JWE token: Decryption failed');

        $this->service->getUserInfo($accessToken, $this->dpopKey, 'openId');
    }

    public function test_get_user_info_throws_exception_on_verification_failure(): void
    {
        Http::fake([
            'https://example.com/userinfo' => Http::response('encrypted-jwe-token', 200),
        ]);

        $this->singPassJwtServiceMock
            ->shouldReceive('jweDecrypt')
            ->once()
            ->with('encrypted-jwe-token')
            ->andReturn('decrypted-jwt-token');

        $mockJwks = JWKSet::createFromKeyData([
            'keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'use' => 'sig', 'n' => 'xGOr-H7A', 'e' => 'AQAB']],
        ]);
        $this->getSingPassJwksServiceMock
            ->shouldReceive('getJwks')
            ->once()
            ->andReturn($mockJwks);

        $this->singPassJwtServiceMock
            ->shouldReceive('jwtDecode')
            ->once()
            ->with('decrypted-jwt-token', $mockJwks)
            ->andThrow(new Exception('Verification failed'));

        $accessToken = $this->createAccessTokenWithScopes(['openid', 'uinfin']);

        $this->expectException(UserInfoVerificationException::class);
        $this->expectExceptionMessage('Failed to verify UserInfo JWT token: Verification failed');

        $this->service->getUserInfo($accessToken, $this->dpopKey, 'openId');
    }

    public function test_get_user_info_extracts_person_info(): void
    {
        Http::fake([
            'https://example.com/userinfo' => Http::response('encrypted-jwe-token', 200),
        ]);

        $this->singPassJwtServiceMock
            ->shouldReceive('jweDecrypt')
            ->once()
            ->with('encrypted-jwe-token')
            ->andReturn('decrypted-jwt-token');

        $mockJwks = JWKSet::createFromKeyData([
            'keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'use' => 'sig', 'n' => 'xGOr-H7A', 'e' => 'AQAB']],
        ]);
        $this->getSingPassJwksServiceMock
            ->shouldReceive('getJwks')
            ->once()
            ->andReturn($mockJwks);

        $personInfo = [
            'uinfin' => ['value' => 'S9000001B', 'source' => '1'],
            'name' => ['value' => 'SOH HAO FENG', 'source' => '1'],
        ];
        $fullPayload = [
            'person_info' => $personInfo,
            'iss' => 'https://id.singpass.gov.sg/fapi',
            'sub' => 'd45d8f21-6178-4713-b962-8635ed2a945a',
            'aud' => 'T5sM5a53Yaw3URyDEv2y9129CbElCN2F',
            'iat' => 1746678089,
        ];

        $this->singPassJwtServiceMock
            ->shouldReceive('jwtDecode')
            ->once()
            ->with('decrypted-jwt-token', $mockJwks)
            ->andReturn($fullPayload);

        $accessToken = $this->createAccessTokenWithScopes(['openid', 'uinfin', 'name']);

        $result = $this->service->getUserInfo($accessToken, $this->dpopKey, 'openId');

        $this->assertEquals($personInfo, $result);
        $uinfin = $result['uinfin'] ?? null;
        $name = $result['name'] ?? null;
        $this->assertIsArray($uinfin);
        $this->assertIsArray($name);
        $this->assertEquals('S9000001B', $uinfin['value']);
        $this->assertEquals('SOH HAO FENG', $name['value']);
    }

    public function test_get_user_info_falls_back_to_full_payload_without_person_info(): void
    {
        Http::fake([
            'https://example.com/userinfo' => Http::response('encrypted-jwe-token', 200),
        ]);

        $this->singPassJwtServiceMock
            ->shouldReceive('jweDecrypt')
            ->once()
            ->andReturn('decrypted-jwt-token');

        $mockJwks = JWKSet::createFromKeyData([
            'keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'use' => 'sig', 'n' => 'xGOr-H7A', 'e' => 'AQAB']],
        ]);
        $this->getSingPassJwksServiceMock
            ->shouldReceive('getJwks')
            ->once()
            ->andReturn($mockJwks);

        $expectedPayload = [
            'sub' => '1234567890',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];
        $this->singPassJwtServiceMock
            ->shouldReceive('jwtDecode')
            ->once()
            ->andReturn($expectedPayload);

        $accessToken = $this->createAccessTokenWithScopes(['openid', 'uinfin', 'regadd']);

        $result = $this->service->getUserInfo($accessToken, $this->dpopKey, 'openId');

        $this->assertEquals($expectedPayload, $result);
    }

    public function test_get_user_info_throws_when_openid_config_missing_from_cache(): void
    {
        Cache::forget('openId');

        $accessToken = $this->createAccessTokenWithScopes(['openid', 'uinfin']);

        $this->expectException(UserInfoRequestException::class);
        $this->expectExceptionMessage('OpenID configuration not found in cache');

        $this->service->getUserInfo($accessToken, $this->dpopKey, 'openId');
    }

    public function test_get_user_info_throws_when_person_info_is_not_an_object(): void
    {
        Http::fake([
            'https://example.com/userinfo' => Http::response('encrypted-jwe-token', 200),
        ]);

        $this->singPassJwtServiceMock
            ->shouldReceive('jweDecrypt')
            ->once()
            ->andReturn('decrypted-jwt-token');

        $mockJwks = JWKSet::createFromKeyData([
            'keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'use' => 'sig', 'n' => 'xGOr-H7A', 'e' => 'AQAB']],
        ]);
        $this->getSingPassJwksServiceMock
            ->shouldReceive('getJwks')
            ->once()
            ->andReturn($mockJwks);

        $this->singPassJwtServiceMock
            ->shouldReceive('jwtDecode')
            ->once()
            ->andReturn(['person_info' => 'unexpected-string']);

        $accessToken = $this->createAccessTokenWithScopes(['openid', 'uinfin']);

        $this->expectException(UserInfoVerificationException::class);
        $this->expectExceptionMessage('UserInfo payload must be a JSON object.');

        $this->service->getUserInfo($accessToken, $this->dpopKey, 'openId');
    }

    public function test_get_user_info_throws_when_result_has_non_string_keys(): void
    {
        Http::fake([
            'https://example.com/userinfo' => Http::response('encrypted-jwe-token', 200),
        ]);

        $this->singPassJwtServiceMock
            ->shouldReceive('jweDecrypt')
            ->once()
            ->andReturn('decrypted-jwt-token');

        $mockJwks = JWKSet::createFromKeyData([
            'keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'use' => 'sig', 'n' => 'xGOr-H7A', 'e' => 'AQAB']],
        ]);
        $this->getSingPassJwksServiceMock
            ->shouldReceive('getJwks')
            ->once()
            ->andReturn($mockJwks);

        $this->singPassJwtServiceMock
            ->shouldReceive('jwtDecode')
            ->once()
            ->andReturn(['sub' => 'x', 0 => 'invalid-key']);

        $accessToken = $this->createAccessTokenWithScopes(['openid', 'uinfin']);

        $this->expectException(UserInfoVerificationException::class);
        $this->expectExceptionMessage('UserInfo payload keys must be strings.');

        $this->service->getUserInfo($accessToken, $this->dpopKey, 'openId');
    }

    // ========== Helper Methods ==========

    /**
     * @param  array<int, string>  $scopes
     */
    private function createAccessTokenWithScopes(array $scopes): string
    {
        $headerJson = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payloadJson = json_encode([
            'sub' => '1234567890',
            'scope' => implode(' ', $scopes),
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $header = base64_encode($headerJson !== false ? $headerJson : '{}');
        $payload = base64_encode($payloadJson !== false ? $payloadJson : '{}');
        $signature = base64_encode('mock-signature');

        return "$header.$payload.$signature";
    }

    private function createAccessTokenWithoutScopes(): string
    {
        $headerJson = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payloadJson = json_encode([
            'sub' => '1234567890',
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $header = base64_encode($headerJson !== false ? $headerJson : '{}');
        $payload = base64_encode($payloadJson !== false ? $payloadJson : '{}');
        $signature = base64_encode('mock-signature');

        return "$header.$payload.$signature";
    }
}
