<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers;

use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class GetAuthenticationEndpointControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_valid_singpass_login_url(): void
    {
        // Mock the HTTP response
        $mockResponse = '{"issuer":"https://example.com","authorization_endpoint":"https://example.com/auth"}';

        Http::fake([
            'https://example.com/discovery' => Http::response($mockResponse, 200),
        ]);

        // Mock configuration values
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.redirect_uri', 'http://redirect.uri');
        Config::set('singpass-login.client_id', 'test-client-id');

        // Call the route
        $response = $this->getJson('/sp/login');

        // Assert response is valid JSON
        $response->assertStatus(200)
            ->assertJsonStructure(['redirect_url']);

        // Extract redirect URL from response
        $redirectUrl = $response->json('redirect_url');

        // Assert the URL contains expected query parameters
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $queryParams);

        $this->assertEquals('http://redirect.uri', $queryParams['redirect_uri']);
        $this->assertEquals('code', $queryParams['response_type']);
        $this->assertStringStartsWith('LOGIN-', $queryParams['state']);
        $this->assertEquals('openid', $queryParams['scope']);
        $this->assertEquals('test-client-id', $queryParams['client_id']);
        $this->assertNotEmpty($queryParams['nonce']); // Ensure nonce is generated
    }
}
