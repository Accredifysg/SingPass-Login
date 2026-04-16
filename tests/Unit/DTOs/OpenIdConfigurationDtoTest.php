<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\DTOs;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Tests\TestCase;

class OpenIdConfigurationDtoTest extends TestCase
{
    public function test_from_discovery_response_creates_dto(): void
    {
        $response = (object) [
            'issuer' => 'https://id.singpass.gov.sg',
            'authorization_endpoint' => 'https://id.singpass.gov.sg/auth',
            'token_endpoint' => 'https://id.singpass.gov.sg/token',
            'userinfo_endpoint' => 'https://id.singpass.gov.sg/userinfo',
            'jwks_uri' => 'https://id.singpass.gov.sg/.well-known/keys',
            'pushed_authorization_request_endpoint' => 'https://id.singpass.gov.sg/par',
        ];

        $dto = OpenIdConfigurationDto::fromDiscoveryResponse($response);

        $this->assertEquals('https://id.singpass.gov.sg', $dto->issuer);
        $this->assertEquals('https://id.singpass.gov.sg/auth', $dto->authorizationEndpoint);
        $this->assertEquals('https://id.singpass.gov.sg/token', $dto->tokenEndpoint);
        $this->assertEquals('https://id.singpass.gov.sg/userinfo', $dto->userinfoEndpoint);
        $this->assertEquals('https://id.singpass.gov.sg/.well-known/keys', $dto->jwksUri);
        $this->assertEquals('https://id.singpass.gov.sg/par', $dto->pushedAuthorizationRequestEndpoint);
    }

    public function test_from_discovery_response_throws_when_all_fields_missing(): void
    {
        $response = (object) [];

        $this->expectException(OpenIdDiscoveryException::class);
        $this->expectExceptionMessage('OpenID discovery response missing required fields: issuer, authorization_endpoint, token_endpoint, userinfo_endpoint, jwks_uri, pushed_authorization_request_endpoint');

        OpenIdConfigurationDto::fromDiscoveryResponse($response);
    }

    public function test_from_discovery_response_throws_when_some_fields_missing(): void
    {
        $response = (object) [
            'issuer' => 'https://id.singpass.gov.sg',
            'authorization_endpoint' => 'https://id.singpass.gov.sg/auth',
        ];

        $this->expectException(OpenIdDiscoveryException::class);
        $this->expectExceptionMessage('token_endpoint, userinfo_endpoint, jwks_uri, pushed_authorization_request_endpoint');

        OpenIdConfigurationDto::fromDiscoveryResponse($response);
    }

    public function test_from_discovery_response_throws_when_field_is_empty_string(): void
    {
        $response = (object) [
            'issuer' => '',
            'authorization_endpoint' => 'https://id.singpass.gov.sg/auth',
            'token_endpoint' => 'https://id.singpass.gov.sg/token',
            'userinfo_endpoint' => 'https://id.singpass.gov.sg/userinfo',
            'jwks_uri' => 'https://id.singpass.gov.sg/.well-known/keys',
            'pushed_authorization_request_endpoint' => 'https://id.singpass.gov.sg/par',
        ];

        $this->expectException(OpenIdDiscoveryException::class);
        $this->expectExceptionMessage('issuer');

        OpenIdConfigurationDto::fromDiscoveryResponse($response);
    }

    public function test_from_discovery_response_ignores_extra_fields(): void
    {
        $response = (object) [
            'issuer' => 'https://id.singpass.gov.sg',
            'authorization_endpoint' => 'https://id.singpass.gov.sg/auth',
            'token_endpoint' => 'https://id.singpass.gov.sg/token',
            'userinfo_endpoint' => 'https://id.singpass.gov.sg/userinfo',
            'jwks_uri' => 'https://id.singpass.gov.sg/.well-known/keys',
            'pushed_authorization_request_endpoint' => 'https://id.singpass.gov.sg/par',
            'scopes_supported' => ['openid'],
            'response_types_supported' => ['code'],
        ];

        $dto = OpenIdConfigurationDto::fromDiscoveryResponse($response);

        $this->assertEquals('https://id.singpass.gov.sg', $dto->issuer);
    }

    public function test_dto_is_readonly(): void
    {
        $dto = new OpenIdConfigurationDto(
            issuer: 'https://example.com',
            authorizationEndpoint: 'https://example.com/auth',
            tokenEndpoint: 'https://example.com/token',
            userinfoEndpoint: 'https://example.com/userinfo',
            jwksUri: 'https://example.com/jwks',
            pushedAuthorizationRequestEndpoint: 'https://example.com/par',
        );

        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadOnly());
    }
}
