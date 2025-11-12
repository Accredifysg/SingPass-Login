<?php

namespace Accredifysg\SingPassLogin\Interfaces;

interface GetUserInfoServiceInterface
{
    /**
     * Retrieve user information from the UserInfo endpoint
     *
     * @param  string  $accessToken  The access token to use for authentication
     * @return array|null The user info data as an associative array, or null if not applicable
     */
    public function getUserInfo(string $accessToken): ?array;

    /**
     * Determine if the UserInfo endpoint should be called based on access token scopes
     *
     * @param  string  $accessToken  The access token JWT to decode
     * @return bool True if UserInfo should be called, false otherwise
     */
    public function shouldCallUserInfo(string $accessToken): bool;
}
