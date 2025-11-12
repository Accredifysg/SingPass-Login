<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Accredifysg\SingPassLogin\Exceptions\SingPassTokenException;
use Accredifysg\SingPassLogin\Services\GetSingPassTokenService;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;

class GetSingPassTokenServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up the cache with a mock OpenId configuration
        Cache::put('openId', (object) [
            'token_endpoint' => 'https://example.com/token',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_token_success(): void
    {
        // Mock configuration values
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');

        // Mock SingPassJwtService methods
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.SingPassJwtService::class);
        $singPassJwtServiceMock->shouldReceive('getSigningJwk')
            ->once()
            ->andReturn($mockJwk);
        $singPassJwtServiceMock->shouldReceive('generateClientAssertion')
            ->once()
            ->with($mockJwk, 'mock-code')
            ->andReturn($mockClientAssertion);

        // Mock the HTTP response
        $mockResponse = [
            'id_token' => 'mock-id-token',
            'access_token' => 'mock-access-token',
        ];

        Http::fake([
            'https://example.com/token' => Http::response($mockResponse, 200),
        ]);

        // Call the method
        $tokenResponse = (new GetSingPassTokenService)->getToken('mock-code', 'test-code-verifier');

        // Assert the method returns the expected TokenResponseDto
        $this->assertInstanceOf(TokenResponseDto::class, $tokenResponse);
        $this->assertEquals('mock-id-token', $tokenResponse->idToken);
        $this->assertEquals('mock-access-token', $tokenResponse->accessToken);
        $this->assertTrue($tokenResponse->hasAccessToken());
    }

    public function test_get_token_without_access_token(): void
    {
        // Mock configuration values
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');

        // Mock SingPassJwtService methods
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.SingPassJwtService::class);
        $singPassJwtServiceMock->shouldReceive('getSigningJwk')
            ->once()
            ->andReturn($mockJwk);
        $singPassJwtServiceMock->shouldReceive('generateClientAssertion')
            ->once()
            ->with($mockJwk, 'mock-code')
            ->andReturn($mockClientAssertion);

        // Mock the HTTP response without access_token
        $mockResponse = [
            'id_token' => 'mock-id-token',
        ];

        Http::fake([
            'https://example.com/token' => Http::response($mockResponse, 200),
        ]);

        // Call the method
        $tokenResponse = (new GetSingPassTokenService)->getToken('mock-code', 'test-code-verifier');

        // Assert the method returns TokenResponseDto with null accessToken
        $this->assertInstanceOf(TokenResponseDto::class, $tokenResponse);
        $this->assertEquals('mock-id-token', $tokenResponse->idToken);
        $this->assertNull($tokenResponse->accessToken);
        $this->assertFalse($tokenResponse->hasAccessToken());
    }

    public function test_get_token_exception(): void
    {
        // Mock configuration values
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');

        // Mock SingPassJwtService methods
        $mockJwk = (object) ['kty' => 'RSA', 'kid' => 'test-key-id'];
        $mockClientAssertion = 'mock-client-assertion';

        $singPassJwtServiceMock = Mockery::mock('alias:'.SingPassJwtService::class);
        $singPassJwtServiceMock->shouldReceive('getSigningJwk')
            ->once()
            ->andReturn($mockJwk);
        $singPassJwtServiceMock->shouldReceive('generateClientAssertion')
            ->once()
            ->with($mockJwk, 'mock-code')
            ->andReturn($mockClientAssertion);

        // Mock the HTTP response to return an error status
        Http::fake([
            'https://example.com/token' => Http::response(null, 500),
        ]);

        // Expect the SingPassTokenException to be thrown
        $this->expectException(SingPassTokenException::class);

        // Call the method
        (new GetSingPassTokenService)->getToken('mock-code', 'test-code-verifier');
    }
}
