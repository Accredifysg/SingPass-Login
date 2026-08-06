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

        $config = OpenIdConfigurationDto::fromCache($cached);

        $this->assertInstanceOf(OpenIdConfigurationDto::class, $config);
        $this->assertEquals('https://example.com', $config->issuer);
        $this->assertEquals('https://example.com/auth', $config->authorizationEndpoint);
        $this->assertEquals('https://example.com/token', $config->tokenEndpoint);
        $this->assertEquals('https://example.com/userinfo', $config->userinfoEndpoint);
        $this->assertEquals('https://example.com/jwks', $config->jwksUri);
        $this->assertEquals('https://example.com/par', $config->pushedAuthorizationRequestEndpoint);
    }

    public function test_cache_open_id_discovery_stores_a_plain_array(): void
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

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:plain-array-test');

        $cached = Cache::get('openId:plain-array-test');

        $this->assertIsArray($cached);

        // Round-tripping through JSON unchanged proves there is no object anywhere in the
        // payload, whatever the cache driver happens to do with serialisation.
        $this->assertSame($cached, json_decode((string) json_encode($cached), true));

        $this->assertSame('https://example.com', $cached['issuer']);
        $this->assertSame('https://example.com/auth', $cached['authorization_endpoint']);
        $this->assertSame('https://example.com/token', $cached['token_endpoint']);
        $this->assertSame('https://example.com/userinfo', $cached['userinfo_endpoint']);
        $this->assertSame('https://example.com/jwks', $cached['jwks_uri']);
        $this->assertSame('https://example.com/par', $cached['pushed_authorization_request_endpoint']);
    }

    /**
     * The v4 → v5 upgrade scenario: production caches still hold the DTO object
     * that v4 serialized. Cache::remember alone would keep returning it until the
     * TTL expired, failing every read in the meantime; the writer must discard it
     * and re-run discovery instead. (An entry rejected by a Laravel 13
     * cache.serializable_classes allowlist deserializes to __PHP_Incomplete_Class
     * and takes the same not-an-array path.)
     */
    public function test_cache_open_id_discovery_replaces_a_stale_serialized_dto_entry(): void
    {
        Cache::put('openId:stale-dto', new OpenIdConfigurationDto(
            issuer: 'https://stale.example.com',
            authorizationEndpoint: 'https://stale.example.com/auth',
            tokenEndpoint: 'https://stale.example.com/token',
            userinfoEndpoint: 'https://stale.example.com/userinfo',
            jwksUri: 'https://stale.example.com/jwks',
            pushedAuthorizationRequestEndpoint: 'https://stale.example.com/par',
        ));

        Http::fake([
            'https://example.com/discovery' => Http::response($this->validDiscoveryResponse(), 200),
        ]);

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:stale-dto');

        $cached = Cache::get('openId:stale-dto');

        $this->assertIsArray($cached);
        $this->assertSame('https://example.com', $cached['issuer']);
    }

    public function test_cache_open_id_discovery_replaces_a_malformed_array_entry(): void
    {
        Cache::put('openId:malformed', ['issuer' => 'https://stale.example.com']);

        Http::fake([
            'https://example.com/discovery' => Http::response($this->validDiscoveryResponse(), 200),
        ]);

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:malformed');

        $cached = Cache::get('openId:malformed');

        $this->assertIsArray($cached);
        $this->assertSame('https://example.com/jwks', $cached['jwks_uri']);
    }

    public function test_cache_open_id_discovery_keeps_a_valid_cached_entry(): void
    {
        $existing = (new OpenIdConfigurationDto(
            issuer: 'https://cached.example.com',
            authorizationEndpoint: 'https://cached.example.com/auth',
            tokenEndpoint: 'https://cached.example.com/token',
            userinfoEndpoint: 'https://cached.example.com/userinfo',
            jwksUri: 'https://cached.example.com/jwks',
            pushedAuthorizationRequestEndpoint: 'https://cached.example.com/par',
        ))->toArray();

        Cache::put('openId:valid', $existing);

        Http::fake();

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:valid');

        Http::assertNothingSent();
        $this->assertSame($existing, Cache::get('openId:valid'));
    }

    private function validDiscoveryResponse(): string
    {
        return (string) json_encode([
            'issuer' => 'https://example.com',
            'authorization_endpoint' => 'https://example.com/auth',
            'token_endpoint' => 'https://example.com/token',
            'userinfo_endpoint' => 'https://example.com/userinfo',
            'jwks_uri' => 'https://example.com/jwks',
            'pushed_authorization_request_endpoint' => 'https://example.com/par',
        ]);
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
