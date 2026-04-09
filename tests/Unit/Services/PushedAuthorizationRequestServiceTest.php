<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\PushedAuthorizationRequestException;
use Accredifysg\SingPassLogin\Services\PushedAuthorizationRequestService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PushedAuthorizationRequestServiceTest extends TestCase
{
    private PushedAuthorizationRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PushedAuthorizationRequestService;

        Cache::put('openId', new OpenIdConfigurationDto(
            issuer: 'https://example.com',
            authorizationEndpoint: 'https://example.com/auth',
            tokenEndpoint: 'https://example.com/token',
            userinfoEndpoint: 'https://example.com/userinfo',
            jwksUri: 'https://example.com/jwks',
            pushedAuthorizationRequestEndpoint: 'https://example.com/fapi/par',
        ));
    }

    public function test_send_request_success(): void
    {
        Http::fake([
            'https://example.com/fapi/par' => Http::response([
                'request_uri' => 'urn:ietf:params:oauth:request_uri:test-uri',
                'expires_in' => 60,
            ], 200),
        ]);

        $params = [
            'response_type' => 'code',
            'scope' => 'openid',
            'client_id' => 'test-client-id',
            'redirect_uri' => 'https://example.com/callback',
            'state' => 'test-state',
            'nonce' => 'test-nonce',
        ];

        $requestUri = $this->service->sendRequest($params, 'mock-dpop-proof-jwt');

        $this->assertEquals('urn:ietf:params:oauth:request_uri:test-uri', $requestUri);
    }

    public function test_send_request_error_response(): void
    {
        Http::fake([
            'https://example.com/fapi/par' => Http::response([
                'error' => 'invalid_request',
                'error_description' => 'The request is missing a required parameter.',
            ], 400),
        ]);

        $params = [
            'response_type' => 'code',
            'scope' => 'openid',
            'client_id' => 'test-client-id',
        ];

        $this->expectException(PushedAuthorizationRequestException::class);

        $this->service->sendRequest($params, 'mock-dpop-proof-jwt');
    }

    public function test_send_request_invalid_dpop(): void
    {
        Http::fake([
            'https://example.com/fapi/par' => Http::response([
                'error' => 'invalid_dpop_proof',
                'error_description' => 'The DPoP header is invalid.',
            ], 400),
        ]);

        $this->expectException(PushedAuthorizationRequestException::class);

        $this->service->sendRequest([], 'invalid-dpop-proof');
    }

    public function test_send_request_missing_request_uri(): void
    {
        Http::fake([
            'https://example.com/fapi/par' => Http::response([
                'expires_in' => 60,
            ], 200),
        ]);

        $this->expectException(PushedAuthorizationRequestException::class);
        $this->expectExceptionMessage('PAR response missing request_uri');

        $this->service->sendRequest([], 'mock-dpop-proof-jwt');
    }

    public function test_send_request_unparseable_response(): void
    {
        Http::fake([
            'https://example.com/fapi/par' => Http::response('not-json', 200),
        ]);

        $this->expectException(PushedAuthorizationRequestException::class);
        $this->expectExceptionMessage('Failed to parse PAR response');

        $this->service->sendRequest([], 'mock-dpop-proof-jwt');
    }
}
