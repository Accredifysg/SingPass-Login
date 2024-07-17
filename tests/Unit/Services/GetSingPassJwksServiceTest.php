<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\Exceptions\SingPassJwksException;
use Accredifysg\SingPassLogin\Services\GetSingPassJwksService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWKSet;
use Orchestra\Testbench\TestCase;

class GetSingPassJwksServiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Set up the cache with a mock OpenId configuration
        Cache::put('openId', (object) [
            'jwks_uri' => 'https://example.com/jwks',
        ]);
    }

    public function test_get_sing_pass_jwks_success()
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
        ]);

        Http::fake([
            'https://example.com/jwks' => Http::response($mockJwks, 200),
        ]);

        // Call the method
        $jwks = GetSingPassJwksService::getSingPassJwks();

        // Assert the method returns a JWKSet object
        $this->assertInstanceOf(JWKSet::class, $jwks);

        // Assert the JWKSet contains the expected keys
        $this->assertEquals($mockJwks, json_encode($jwks->jsonSerialize()));
    }

    public function test_get_sing_pass_jwks_exception()
    {
        // Mock the HTTP response to return an error status
        Http::fake([
            'https://example.com/jwks' => Http::response(null, 500),
        ]);

        // Expect the SingPassJwksException to be thrown
        $this->expectException(SingPassJwksException::class);

        // Call the method
        GetSingPassJwksService::getSingPassJwks();
    }
}
