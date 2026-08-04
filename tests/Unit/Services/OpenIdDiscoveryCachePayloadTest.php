<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Services\JwksService;
use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Exercises the OpenID discovery cache against a *serializing* store (file) with
 * `cache.serializable_classes` set to false — the default for new Laravel 13
 * applications, which unserializes cache payloads with `allowed_classes: false`.
 *
 * The default array store keeps live PHP objects and so cannot catch a payload
 * that fails to survive serialization. On Laravel 11/12 the config key is simply
 * ignored and these assertions still hold.
 */
class OpenIdDiscoveryCachePayloadTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $this->appConfigSet($app, 'cache.default', 'file');
        $this->appConfigSet($app, 'cache.serializable_classes', false);
    }

    public function test_discovery_cache_round_trips_through_a_serializing_store(): void
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

        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery('https://example.com/discovery', 'openId:payload');

        $config = OpenIdConfigurationDto::fromCache(Cache::get('openId:payload'));

        $this->assertInstanceOf(OpenIdConfigurationDto::class, $config);
        $this->assertSame('https://example.com/jwks', $config->jwksUri);

        // A downstream consumer must be able to work off the same cache entry.
        $this->assertCount(0, (new JwksService)->getJwks('openId:payload'));
    }
}
