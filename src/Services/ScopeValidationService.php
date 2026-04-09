<?php

namespace Accredifysg\SingPassLogin\Services;

use InvalidArgumentException;

class ScopeValidationService
{
    /**
     * Parse, validate, and normalize scopes from request input
     *
     * @param  string|array<int, string>  $scopes
     * @return array<int, string>
     *
     * @throws InvalidArgumentException
     */
    public function parseAndValidate(string|array $scopes): array
    {
        $scopesArray = $this->parseScopes($scopes);
        $this->validateScopes($scopesArray);

        return $this->normalizeScopes($scopesArray);
    }

    /**
     * Format validated scopes for OAuth 2.0 authorization URL
     *
     * @param  array<int, string>  $scopes
     */
    public function formatForOAuth(array $scopes): string
    {
        return implode(' ', $scopes);
    }

    /**
     * Parse scopes from string (comma-separated) or array format
     *
     * @param  string|array<int, string>  $scopes
     * @return array<int, string>
     */
    private function parseScopes(string|array $scopes): array
    {
        if (is_array($scopes)) {
            return $scopes;
        }

        return explode(',', $scopes);
    }

    /**
     * Validate scopes against available scopes configuration
     *
     * @param  array<int, string>  $scopes
     *
     * @throws InvalidArgumentException
     */
    private function validateScopes(array $scopes): void
    {
        $availableScopes = $this->getAvailableScopes();

        foreach ($scopes as $scope) {
            if (! in_array($scope, $availableScopes)) {
                throw new InvalidArgumentException(
                    "Invalid scope requested: '{$scope}'. Available scopes: ".implode(', ', $availableScopes)
                );
            }
        }
    }

    /**
     * Normalize scopes to ensure 'openid' is always included as first scope
     *
     * @param  array<int, string>  $scopes
     * @return array<int, string>
     */
    private function normalizeScopes(array $scopes): array
    {
        // Ensure openid is always included
        if (! in_array('openid', $scopes)) {
            array_unshift($scopes, 'openid');
        }

        return $scopes;
    }

    /**
     * Determine whether the given scopes contain any MyInfo scopes
     * (i.e. scopes that require a UserInfo endpoint call).
     *
     * @param  array<int, string>  $scopes
     */
    public function hasMyInfoScopes(array $scopes): bool
    {
        $loginScopes = $this->getLoginScopes();

        foreach ($scopes as $scope) {
            if (! in_array($scope, $loginScopes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get login scopes from configuration.
     * These scopes are returned in the ID token and do not require UserInfo.
     *
     * @return array<int, string>
     */
    private function getLoginScopes(): array
    {
        return config('singpass-login.login_scopes', [
            'openid',
            'user.identity',
            'name',
            'email',
            'mobileno',
        ]);
    }

    /**
     * Get available scopes from configuration
     * Always includes 'openid' scope
     *
     * @return array<int, string>
     */
    private function getAvailableScopes(): array
    {
        $availableScopes = config('singpass-login.available_scopes', []);

        // Always allow 'openid'
        if (! in_array('openid', $availableScopes)) {
            $availableScopes[] = 'openid';
        }

        return $availableScopes;
    }
}
