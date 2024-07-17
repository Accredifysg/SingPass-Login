<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\Exceptions\SingPassTokenException;
use Accredifysg\SingPassLogin\Services\GetSingPassTokenService;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Orchestra\Testbench\TestCase;

class GetSingPassTokenServiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Set up the cache with a mock OpenId configuration
        Cache::put('openId', (object) [
            'token_endpoint' => 'https://example.com/token',
        ]);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_token_success()
    {
        // Mock configuration values
        Config::set('services.singpass-login.clientId', 'test-client-id');
        Config::set('services.singpass-login.redirectionUrl', 'https://example.com/callback');

        // Mock SingPassJwtService methods
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.SingPassJwtService::class);
        $singPassJwtServiceMock->shouldReceive('getSigningJwk')
            ->once()
            ->andReturn($mockJwk);
        $singPassJwtServiceMock->shouldReceive('generateClientAssertion')
            ->once()
            ->with($mockJwk)
            ->andReturn($mockClientAssertion);

        // Mock the HTTP response
        $mockResponse = [
            'id_token' => 'mock-id-token',
        ];

        Http::fake([
            'https://example.com/token' => Http::response($mockResponse, 200),
        ]);

        // Call the method
        $token = GetSingPassTokenService::getToken('mock-code');

        // Assert the method returns the expected token
        $this->assertEquals('mock-id-token', $token);
    }

    public function test_get_token_exception()
    {
        // Mock configuration values
        Config::set('services.singpass-login.clientId', 'test-client-id');
        Config::set('services.singpass-login.redirectionUrl', 'https://example.com/callback');

        // Mock SingPassJwtService methods
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.SingPassJwtService::class);
        $singPassJwtServiceMock->shouldReceive('getSigningJwk')
            ->once()
            ->andReturn($mockJwk);
        $singPassJwtServiceMock->shouldReceive('generateClientAssertion')
            ->once()
            ->with($mockJwk)
            ->andReturn($mockClientAssertion);

        // Mock the HTTP response to return an error status
        Http::fake([
            'https://example.com/token' => Http::response(null, 500),
        ]);

        // Expect the SingPassTokenException to be thrown
        $this->expectException(SingPassTokenException::class);

        // Call the method
        GetSingPassTokenService::getToken('mock-code');
    }
}
