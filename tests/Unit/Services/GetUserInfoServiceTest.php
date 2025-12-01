<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Accredifysg\SingPassLogin\Services\GetUserInfoService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWKSet;
use Mockery;
use Mockery\MockInterface;

class GetUserInfoServiceTest extends TestCase
{
    private SingPassJwtServiceInterface&MockInterface $singPassJwtServiceMock;

    private GetSingPassJwksServiceInterface&MockInterface $getSingPassJwksServiceMock;

    private GetUserInfoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SingPassJwtServiceInterface&MockInterface $singPassJwtServiceMock */
        $singPassJwtServiceMock = Mockery::mock(SingPassJwtServiceInterface::class);
        $this->singPassJwtServiceMock = $singPassJwtServiceMock;

        /** @var GetSingPassJwksServiceInterface&MockInterface $getSingPassJwksServiceMock */
        $getSingPassJwksServiceMock = Mockery::mock(GetSingPassJwksServiceInterface::class);
        $this->getSingPassJwksServiceMock = $getSingPassJwksServiceMock;

        $this->service = new GetUserInfoService(
            $this->singPassJwtServiceMock,
            $this->getSingPassJwksServiceMock
        );

        // Set up the cache with a mock OpenId configuration
        Cache::put('openId', (object) [
            'userinfo_endpoint' => 'https://example.com/userinfo',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========== shouldCallUserInfo() Tests ==========

    public function test_should_call_user_info_returns_false_for_openid_only_scope(): void
    {
        // Access token with only 'openid' scope
        $accessToken = $this->createAccessTokenWithScopes(['openid']);

        $result = $this->service->shouldCallUserInfo($accessToken);

        $this->assertFalse($result);
    }

    public function test_should_call_user_info_returns_true_for_multiple_scopes(): void
    {
        // Access token with 'openid' and other scopes
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'profile']);

        $result = $this->service->shouldCallUserInfo($accessToken);

        $this->assertTrue($result);
    }

    public function test_should_call_user_info_returns_true_for_non_openid_scope(): void
    {
        // Access token with only non-openid scope
        $accessToken = $this->createAccessTokenWithScopes(['profile']);

        $result = $this->service->shouldCallUserInfo($accessToken);

        $this->assertTrue($result);
    }

    public function test_should_call_user_info_returns_true_for_openid_and_email_scopes(): void
    {
        // Access token with 'openid' and 'email' scopes
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'email']);

        $result = $this->service->shouldCallUserInfo($accessToken);

        $this->assertTrue($result);
    }

    public function test_should_call_user_info_handles_malformed_token(): void
    {
        // Malformed token (not 3 parts)
        $accessToken = 'invalid.token';

        $result = $this->service->shouldCallUserInfo($accessToken);

        // Should default to false (only openid scope)
        $this->assertFalse($result);
    }

    public function test_should_call_user_info_handles_token_without_scope(): void
    {
        // Token without scope claim
        $accessToken = $this->createAccessTokenWithoutScopes();

        $result = $this->service->shouldCallUserInfo($accessToken);

        // Should default to false (only openid scope)
        $this->assertFalse($result);
    }

    // ========== getUserInfo() Tests ==========

    public function test_get_user_info_returns_null_when_should_not_call(): void
    {
        // Access token with only 'openid' scope
        $accessToken = $this->createAccessTokenWithScopes(['openid']);

        $result = $this->service->getUserInfo($accessToken);

        $this->assertNull($result);
    }

    public function test_get_user_info_throws_exception_when_endpoint_not_in_cache(): void
    {
        // Clear cache
        Cache::forget('openId');

        // Access token with multiple scopes
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'profile']);

        $this->expectException(UserInfoRequestException::class);
        $this->expectExceptionMessage('UserInfo endpoint not found in OpenID discovery.');

        $this->service->getUserInfo($accessToken);
    }

    public function test_get_user_info_throws_exception_when_userinfo_endpoint_missing(): void
    {
        // Cache without userinfo_endpoint
        Cache::put('openId', (object) [
            'issuer' => 'https://example.com',
        ]);

        // Access token with multiple scopes
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'profile']);

        $this->expectException(UserInfoRequestException::class);
        $this->expectExceptionMessage('UserInfo endpoint not found in OpenID discovery.');

        $this->service->getUserInfo($accessToken);
    }

    public function test_get_user_info_throws_exception_on_http_failure(): void
    {
        // Mock HTTP response to fail
        Http::fake([
            'https://example.com/userinfo' => Http::response(null, 500),
        ]);

        // Access token with multiple scopes
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'profile']);

        $this->expectException(UserInfoRequestException::class);
        $this->expectExceptionMessage('UserInfo endpoint request failed with status 500');

        $this->service->getUserInfo($accessToken);
    }

    public function test_get_user_info_throws_exception_on_decryption_failure(): void
    {
        // Mock successful HTTP response
        Http::fake([
            'https://example.com/userinfo' => Http::response('encrypted-jwe-token', 200),
        ]);

        // Mock JWE decryption to throw exception
        $this->singPassJwtServiceMock
            ->shouldReceive('jweDecrypt')
            ->once()
            ->with('encrypted-jwe-token')
            ->andThrow(new Exception('Decryption failed'));

        // Access token with multiple scopes
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'profile']);

        $this->expectException(UserInfoDecryptionException::class);
        $this->expectExceptionMessage('Failed to decrypt UserInfo JWE token: Decryption failed');

        $this->service->getUserInfo($accessToken);
    }

    public function test_get_user_info_throws_exception_on_verification_failure(): void
    {
        // Mock successful HTTP response
        Http::fake([
            'https://example.com/userinfo' => Http::response('encrypted-jwe-token', 200),
        ]);

        // Mock successful JWE decryption
        $this->singPassJwtServiceMock
            ->shouldReceive('jweDecrypt')
            ->once()
            ->with('encrypted-jwe-token')
            ->andReturn('decrypted-jwt-token');

        // Mock JWKS retrieval
        $mockJwks = JWKSet::createFromKeyData([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'test-key',
                    'use' => 'sig',
                    'n' => 'xGOr-H7A',
                    'e' => 'AQAB',
                ],
            ],
        ]);
        $this->getSingPassJwksServiceMock
            ->shouldReceive('getSingPassJwks')
            ->once()
            ->andReturn($mockJwks);

        // Mock JWT verification to throw exception
        $this->singPassJwtServiceMock
            ->shouldReceive('jwtDecode')
            ->once()
            ->with('decrypted-jwt-token', $mockJwks)
            ->andThrow(new Exception('Verification failed'));

        // Access token with multiple scopes
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'profile']);

        $this->expectException(UserInfoVerificationException::class);
        $this->expectExceptionMessage('Failed to verify UserInfo JWT token: Verification failed');

        $this->service->getUserInfo($accessToken);
    }

    public function test_get_user_info_success(): void
    {
        // Mock successful HTTP response
        Http::fake([
            'https://example.com/userinfo' => Http::response('encrypted-jwe-token', 200),
        ]);

        // Mock successful JWE decryption
        $this->singPassJwtServiceMock
            ->shouldReceive('jweDecrypt')
            ->once()
            ->with('encrypted-jwe-token')
            ->andReturn('decrypted-jwt-token');

        // Mock JWKS retrieval
        $mockJwks = JWKSet::createFromKeyData([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'test-key',
                    'use' => 'sig',
                    'n' => 'xGOr-H7A',
                    'e' => 'AQAB',
                ],
            ],
        ]);
        $this->getSingPassJwksServiceMock
            ->shouldReceive('getSingPassJwks')
            ->once()
            ->andReturn($mockJwks);

        // Mock successful JWT verification and decoding
        $expectedPayload = [
            'sub' => '1234567890',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];
        $this->singPassJwtServiceMock
            ->shouldReceive('jwtDecode')
            ->once()
            ->with('decrypted-jwt-token', $mockJwks)
            ->andReturn($expectedPayload);

        // Access token with multiple scopes
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'profile', 'email']);

        $result = $this->service->getUserInfo($accessToken);

        $this->assertIsArray($result);
        $this->assertEquals($expectedPayload, $result);
        $this->assertEquals('1234567890', $result['sub']);
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    public function test_get_user_info_success_with_complex_payload(): void
    {
        // Mock successful HTTP response
        Http::fake([
            'https://example.com/userinfo' => Http::response('encrypted-jwe-token', 200),
        ]);

        // Mock successful JWE decryption
        $this->singPassJwtServiceMock
            ->shouldReceive('jweDecrypt')
            ->once()
            ->andReturn('decrypted-jwt-token');

        // Mock JWKS retrieval
        $mockJwks = JWKSet::createFromKeyData([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'test-key',
                    'use' => 'sig',
                    'n' => 'xGOr-H7A',
                    'e' => 'AQAB',
                ],
            ],
        ]);
        $this->getSingPassJwksServiceMock
            ->shouldReceive('getSingPassJwks')
            ->once()
            ->andReturn($mockJwks);

        // Mock successful JWT verification with complex payload
        $expectedPayload = [
            'sub' => '1234567890',
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'birthdate' => '1990-01-01',
            'address' => [
                'street_address' => '123 Main St',
                'locality' => 'Springfield',
                'postal_code' => '12345',
            ],
        ];
        $this->singPassJwtServiceMock
            ->shouldReceive('jwtDecode')
            ->once()
            ->andReturn($expectedPayload);

        // Access token with multiple scopes
        $accessToken = $this->createAccessTokenWithScopes(['openid', 'profile', 'email', 'address']);

        $result = $this->service->getUserInfo($accessToken);

        $this->assertIsArray($result);
        $this->assertEquals($expectedPayload, $result);
        $this->assertArrayHasKey('address', $result);
        $this->assertIsArray($result['address']);
        $this->assertEquals('Springfield', $result['address']['locality']);
    }

    // ========== Helper Methods ==========

    /**
     * Create a mock access token with specified scopes
     *
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

    /**
     * Create a mock access token without scope claim
     */
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
