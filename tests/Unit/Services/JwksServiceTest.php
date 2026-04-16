<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\JwksException;
use Accredifysg\SingPassLogin\Services\JwksService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWKSet;

class JwksServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Set up the cache with a mock OpenId configuration
        Cache::put('openId', new OpenIdConfigurationDto(
            issuer: 'https://example.com',
            authorizationEndpoint: 'https://example.com/auth',
            tokenEndpoint: 'https://example.com/token',
            userinfoEndpoint: 'https://example.com/userinfo',
            jwksUri: 'https://example.com/jwks',
            pushedAuthorizationRequestEndpoint: 'https://example.com/par',
        ));
    }

    public function test_get_jwks_success(): void
    {
        // Mock the HTTP response
        $mockJwks = json_encode([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => '1b94c',
                    'use' => 'sig',
                    'n' => '...',
                    'e' => 'AQAB',
                ],
            ],
        ]) ?: '{}';

        Http::fake([
            'https://example.com/jwks' => Http::response($mockJwks, 200),
        ]);

        // Call the method
        $jwks = (new JwksService)->getJwks('openId');

        // Assert the method returns a JWKSet object
        $this->assertInstanceOf(JWKSet::class, $jwks);

        // Assert the JWKSet contains the expected keys
        $this->assertEquals($mockJwks, json_encode($jwks->jsonSerialize()));
    }

    public function test_get_jwks_exception(): void
    {
        // Mock the HTTP response to return an error status
        Http::fake([
            'https://example.com/jwks' => Http::response(null, 500),
        ]);

        // Expect the JwksException to be thrown
        $this->expectException(JwksException::class);

        // Call the method
        (new JwksService)->getJwks('openId');
    }

    public function test_get_jwks_throws_when_openid_config_missing_from_cache(): void
    {
        Cache::forget('openId');

        $this->expectException(JwksException::class);
        $this->expectExceptionMessage('OpenID configuration not found in cache');

        (new JwksService)->getJwks('openId');
    }
}
