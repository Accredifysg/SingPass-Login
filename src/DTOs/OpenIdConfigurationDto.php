<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\DTOs;

use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Support\TypeNarrow;

readonly class OpenIdConfigurationDto
{
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $userinfoEndpoint,
        public string $jwksUri,
        public string $pushedAuthorizationRequestEndpoint,
    ) {}

    /**
     * Build from a decoded OpenID Connect discovery response, validating that
     * all required fields are present.
     *
     * @param  object  $response  The decoded JSON response from the discovery endpoint
     *
     * @throws OpenIdDiscoveryException
     */
    public static function fromDiscoveryResponse(object $response): self
    {
        /** @var array<string, mixed> $data */
        $data = (array) $response;

        $required = [
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'userinfo_endpoint',
            'jwks_uri',
            'pushed_authorization_request_endpoint',
        ];

        $missing = [];

        foreach ($required as $field) {
            $value = $data[$field] ?? null;
            if (! is_string($value) || $value === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new OpenIdDiscoveryException(
                500,
                'OpenID discovery response missing required fields: '.implode(', ', $missing),
            );
        }

        return new self(
            issuer: TypeNarrow::nonEmptyString($data, 'issuer')
                ?? throw new OpenIdDiscoveryException(500, 'OpenID discovery response has invalid non-string field: issuer'),
            authorizationEndpoint: TypeNarrow::nonEmptyString($data, 'authorization_endpoint')
                ?? throw new OpenIdDiscoveryException(500, 'OpenID discovery response has invalid non-string field: authorization_endpoint'),
            tokenEndpoint: TypeNarrow::nonEmptyString($data, 'token_endpoint')
                ?? throw new OpenIdDiscoveryException(500, 'OpenID discovery response has invalid non-string field: token_endpoint'),
            userinfoEndpoint: TypeNarrow::nonEmptyString($data, 'userinfo_endpoint')
                ?? throw new OpenIdDiscoveryException(500, 'OpenID discovery response has invalid non-string field: userinfo_endpoint'),
            jwksUri: TypeNarrow::nonEmptyString($data, 'jwks_uri')
                ?? throw new OpenIdDiscoveryException(500, 'OpenID discovery response has invalid non-string field: jwks_uri'),
            pushedAuthorizationRequestEndpoint: TypeNarrow::nonEmptyString($data, 'pushed_authorization_request_endpoint')
                ?? throw new OpenIdDiscoveryException(500, 'OpenID discovery response has invalid non-string field: pushed_authorization_request_endpoint'),
        );
    }

    /**
     * Flatten to a plain array for caching. Keys match the discovery response so
     * the payload can be fed straight back through fromDiscoveryResponse().
     *
     * @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: string, jwks_uri: string, pushed_authorization_request_endpoint: string}
     */
    public function toArray(): array
    {
        return [
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->authorizationEndpoint,
            'token_endpoint' => $this->tokenEndpoint,
            'userinfo_endpoint' => $this->userinfoEndpoint,
            'jwks_uri' => $this->jwksUri,
            'pushed_authorization_request_endpoint' => $this->pushedAuthorizationRequestEndpoint,
        ];
    }

    /**
     * Rehydrate from a cached payload, returning null for anything unusable — a
     * miss, a stale shape, or a value left by an older version of the package.
     * Callers throw their own service-specific exception on null.
     */
    public static function fromCache(mixed $cached): ?self
    {
        if (! is_array($cached)) {
            return null;
        }

        try {
            return self::fromDiscoveryResponse((object) $cached);
        } catch (OpenIdDiscoveryException) {
            return null;
        }
    }
}
