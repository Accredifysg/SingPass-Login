<?php

namespace Accredifysg\SingPassLogin\DTOs;

use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;

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
            if (! isset($data[$field]) || $data[$field] === '') {
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
            issuer: $data['issuer'],
            authorizationEndpoint: $data['authorization_endpoint'],
            tokenEndpoint: $data['token_endpoint'],
            userinfoEndpoint: $data['userinfo_endpoint'],
            jwksUri: $data['jwks_uri'],
            pushedAuthorizationRequestEndpoint: $data['pushed_authorization_request_endpoint'],
        );
    }
}
