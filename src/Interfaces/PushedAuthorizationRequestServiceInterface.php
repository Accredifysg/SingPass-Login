<?php

namespace Accredifysg\SingPassLogin\Interfaces;

interface PushedAuthorizationRequestServiceInterface
{
    /**
     * Send a Pushed Authorization Request and return the request_uri.
     *
     * @param  array<string, string>  $params  The authorization request parameters
     * @param  string  $dpopProofJwt  The DPoP proof JWT for this request
     * @param  string  $cacheKey  The cache key for the provider's OpenID configuration
     * @return string The request_uri from the PAR response
     */
    public function sendRequest(array $params, string $dpopProofJwt, string $cacheKey): string;
}
