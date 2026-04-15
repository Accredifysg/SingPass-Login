<?php

namespace Accredifysg\SingPassLogin\Services;

use InvalidArgumentException;

class ScopeValidationService
{
    /**
     * Parse, validate, and normalize scopes from request input.
     *
     * @param  string|array<int, string>  $scopes
     * @param  array<int, string>  $availableScopes
     * @return array<int, string>
     *
     * @throws InvalidArgumentException
     */
    public function parseAndValidate(string|array $scopes, array $availableScopes): array
    {
        $scopesArray = $this->parseScopes($scopes);
        $this->validateScopes($scopesArray, $availableScopes);

        return $this->normalizeScopes($scopesArray);
    }

    /**
     * Format validated scopes for OAuth 2.0 authorization URL.
     *
     * @param  array<int, string>  $scopes
     */
    public function formatForOAuth(array $scopes): string
    {
        return implode(' ', $scopes);
    }

    /**
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
     * @param  array<int, string>  $scopes
     * @param  array<int, string>  $availableScopes
     *
     * @throws InvalidArgumentException
     */
    private function validateScopes(array $scopes, array $availableScopes): void
    {
        if (! in_array('openid', $availableScopes)) {
            $availableScopes[] = 'openid';
        }

        foreach ($scopes as $scope) {
            if (! in_array($scope, $availableScopes)) {
                throw new InvalidArgumentException(
                    "Invalid scope requested: '{$scope}'. Available scopes: ".implode(', ', $availableScopes)
                );
            }
        }
    }

    /**
     * @param  array<int, string>  $scopes
     * @return array<int, string>
     */
    private function normalizeScopes(array $scopes): array
    {
        if (! in_array('openid', $scopes)) {
            array_unshift($scopes, 'openid');
        }

        return $scopes;
    }
}
