<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenIdDiscoveryServiceTest extends TestCase
{
    public function test_cache_open_id_discovery_success(): void
    {
        $mockResponse = (string) json_encode([
            'issuer' => 'https://example.com',
            'authorization_endpoint' => 'https://example.com/auth',
            'token_endpoint' => 'https://example.com/token',
            'userinfo_endpoint' => 'https://example.com/userinfo',
            'jwks_uri' => 'https://example.com/jwks',
            'pushed_authorization_request_endpoint' => 'https://example.com/par',
        ]);

        Http::fake([
            'https://example.com/discovery' => Http::response($mockResponse, 200),
        ]);

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:singpass');

        $cached = Cache::get('openId:singpass');

        $this->assertInstanceOf(OpenIdConfigurationDto::class, $cached);
        $this->assertEquals('https://example.com', $cached->issuer);
        $this->assertEquals('https://example.com/auth', $cached->authorizationEndpoint);
        $this->assertEquals('https://example.com/token', $cached->tokenEndpoint);
        $this->assertEquals('https://example.com/userinfo', $cached->userinfoEndpoint);
        $this->assertEquals('https://example.com/jwks', $cached->jwksUri);
        $this->assertEquals('https://example.com/par', $cached->pushedAuthorizationRequestEndpoint);
    }

    public function test_cache_open_id_discovery_missing_required_fields(): void
    {
        $mockResponse = (string) json_encode([
            'issuer' => 'https://example.com',
            'authorization_endpoint' => 'https://example.com/auth',
        ]);

        Http::fake([
            'https://example.com/discovery' => Http::response($mockResponse, 200),
        ]);

        $this->expectException(OpenIdDiscoveryException::class);
        $this->expectExceptionMessage('OpenID discovery response missing required fields: token_endpoint, userinfo_endpoint, jwks_uri, pushed_authorization_request_endpoint');

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:singpass');
    }

    public function test_cache_open_id_discovery_json_exception(): void
    {
        Http::fake([
            'https://example.com/discovery' => Http::response('invalid json', 200),
        ]);

        $this->expectException(OpenIdDiscoveryException::class);
        $this->expectExceptionMessage('Open ID Discovery response parse failure.');

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:singpass');
    }

    public function test_cache_open_id_discovery_rejects_json_array(): void
    {
        Http::fake([
            'https://example.com/discovery' => Http::response('[]', 200),
        ]);

        $this->expectException(OpenIdDiscoveryException::class);
        $this->expectExceptionMessage('Open ID Discovery JSON must be an object.');

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:json-array-test');
    }

    public function test_cache_open_id_discovery_exception(): void
    {
        Http::fake([
            'https://example.com/discovery' => Http::response('invalid json', 500),
        ]);

        $this->expectException(OpenIdDiscoveryException::class);
        $this->expectExceptionMessage('Open ID Discovery call failed');

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:singpass');
    }
}
