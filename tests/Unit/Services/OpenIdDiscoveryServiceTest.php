<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class OpenIdDiscoveryServiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // You can set any necessary configuration here.
        Config::set('services.singpass-login.discovery_endpoint', 'https://example.com/discovery');
    }

    public function test_cache_open_id_discovery_success()
    {
        // Mock the HTTP response
        $mockResponse = '{"issuer":"https://example.com","authorization_endpoint":"https://example.com/auth"}';
        $cacheObject = json_decode('{"issuer":"https://example.com","authorization_endpoint":"https://example.com/auth"}', false);

        Http::fake([
            'https://example.com/discovery' => Http::response($mockResponse, 200),
        ]);

        // Call the method
        OpenIdDiscoveryService::cacheOpenIdDiscovery();

        // Assert the data is cached
        $this->assertEquals($cacheObject, Cache::get('openId'));
    }

    public function test_cache_open_id_discovery_exception()
    {
        // Mock the HTTP response to return invalid JSON
        Http::fake([
            'https://example.com/discovery' => Http::response('invalid json', 200),
        ]);

        // Expect the OpenIdDiscoveryException to be thrown
        $this->expectException(OpenIdDiscoveryException::class);

        // Call the method
        OpenIdDiscoveryService::cacheOpenIdDiscovery();
    }
}
