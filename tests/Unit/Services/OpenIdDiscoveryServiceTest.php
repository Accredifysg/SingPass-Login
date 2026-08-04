<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Services\JwksService;
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

        // Must be a plain array, not a serialized DTO: Laravel 13 defaults
        // cache.serializable_classes to false, so an object payload would return as
        // __PHP_Incomplete_Class on every serializing store.
        $this->assertIsArray($cached);

        $config = OpenIdConfigurationDto::fromCache($cached);

        $this->assertInstanceOf(OpenIdConfigurationDto::class, $config);
        $this->assertEquals('https://example.com', $config->issuer);
        $this->assertEquals('https://example.com/auth', $config->authorizationEndpoint);
        $this->assertEquals('https://example.com/token', $config->tokenEndpoint);
        $this->assertEquals('https://example.com/userinfo', $config->userinfoEndpoint);
        $this->assertEquals('https://example.com/jwks', $config->jwksUri);
        $this->assertEquals('https://example.com/par', $config->pushedAuthorizationRequestEndpoint);
    }

    public function test_cached_payload_survives_restricted_unserialization(): void
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

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:restricted');

        // Mirror what Laravel 13's cache stores do when serializable_classes is false.
        $roundTripped = unserialize(
            serialize(Cache::get('openId:restricted')),
            ['allowed_classes' => false],
        );

        $config = OpenIdConfigurationDto::fromCache($roundTripped);

        $this->assertInstanceOf(OpenIdConfigurationDto::class, $config);
        $this->assertEquals('https://example.com/jwks', $config->jwksUri);
    }

    public function test_unreadable_cache_entry_is_replaced_rather_than_reused(): void
    {
        // A serialized DTO written by an older release, read back under Laravel 13's
        // restricted unserialize: non-null, but unusable. Cache::remember() would keep
        // returning it until the TTL expired, breaking logins for up to an hour.
        $poisoned = unserialize(
            serialize(new OpenIdConfigurationDto(
                issuer: 'https://stale.example.com',
                authorizationEndpoint: 'https://stale.example.com/auth',
                tokenEndpoint: 'https://stale.example.com/token',
                userinfoEndpoint: 'https://stale.example.com/userinfo',
                jwksUri: 'https://stale.example.com/jwks',
                pushedAuthorizationRequestEndpoint: 'https://stale.example.com/par',
            )),
            ['allowed_classes' => false],
        );

        Cache::put('openId:poisoned', $poisoned, now()->addHour());

        $mockResponse = (string) json_encode([
            'issuer' => 'https://fresh.example.com',
            'authorization_endpoint' => 'https://fresh.example.com/auth',
            'token_endpoint' => 'https://fresh.example.com/token',
            'userinfo_endpoint' => 'https://fresh.example.com/userinfo',
            'jwks_uri' => 'https://fresh.example.com/jwks',
            'pushed_authorization_request_endpoint' => 'https://fresh.example.com/par',
        ]);

        Http::fake([
            'https://example.com/discovery' => Http::response($mockResponse, 200),
        ]);

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:poisoned');

        $config = OpenIdConfigurationDto::fromCache(Cache::get('openId:poisoned'));

        $this->assertInstanceOf(OpenIdConfigurationDto::class, $config);
        $this->assertEquals('https://fresh.example.com', $config->issuer);
    }

    public function test_valid_cache_entry_is_not_refetched(): void
    {
        Cache::put('openId:warm', [
            'issuer' => 'https://warm.example.com',
            'authorization_endpoint' => 'https://warm.example.com/auth',
            'token_endpoint' => 'https://warm.example.com/token',
            'userinfo_endpoint' => 'https://warm.example.com/userinfo',
            'jwks_uri' => 'https://warm.example.com/jwks',
            'pushed_authorization_request_endpoint' => 'https://warm.example.com/par',
        ], now()->addHour());

        Http::fake();

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:warm');

        Http::assertNothingSent();
    }

    /**
     * Regression test for the Laravel 13 cache break, written entirely against the
     * public API so it is meaningful against any version of this package.
     *
     * Caching the DTO object made this fail: the round trip below is what a
     * serializing store does under `cache.serializable_classes => false`, and an
     * object payload comes back as `__PHP_Incomplete_Class`, so every consumer of
     * the discovery cache threw "OpenID configuration not found in cache".
     *
     * The simulation is explicit rather than relying on framework config, so the
     * guard holds on Laravel 11 and 12 too, where the option does not yet exist.
     */
    public function test_discovery_consumers_work_when_the_store_forbids_unserializing_classes(): void
    {
        Http::fake([
            'https://example.com/discovery' => Http::response((string) json_encode([
                'issuer' => 'https://example.com',
                'authorization_endpoint' => 'https://example.com/auth',
                'token_endpoint' => 'https://example.com/token',
                'userinfo_endpoint' => 'https://example.com/userinfo',
                'jwks_uri' => 'https://example.com/jwks',
                'pushed_authorization_request_endpoint' => 'https://example.com/par',
            ]), 200),
            'https://example.com/jwks' => Http::response((string) json_encode(['keys' => []]), 200),
        ]);

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:restricted-store');

        Cache::put(
            'openId:restricted-store',
            unserialize(serialize(Cache::get('openId:restricted-store')), ['allowed_classes' => false]),
            now()->addHour(),
        );

        // No exception here is the whole point — this is what broke on Laravel 13.
        $this->assertCount(0, (new JwksService)->getJwks('openId:restricted-store'));
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
