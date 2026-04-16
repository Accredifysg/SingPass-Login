<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Interfaces;

use Jose\Component\Core\JWK;

interface GetUserInfoServiceInterface
{
    /**
     * Retrieve user information from the UserInfo endpoint.
     *
     * @param  string  $accessToken  The access token to use for authentication
     * @param  JWK  $dpopKey  The DPoP private key for proof generation
     * @param  string  $cacheKey  Cache key for the provider's OpenID configuration
     * @return array<string, mixed> The user info data as an associative array
     */
    public function getUserInfo(string $accessToken, JWK $dpopKey, string $cacheKey): array;

    /**
     * Determine if the UserInfo endpoint should be called based on access token scopes.
     *
     * @param  string  $accessToken  The access token JWT to decode
     * @param  array<int, string>  $loginScopes  Scopes fulfilled by the ID token
     * @return bool True if UserInfo should be called, false otherwise
     */
    public function shouldCallUserInfo(string $accessToken, array $loginScopes): bool;
}
