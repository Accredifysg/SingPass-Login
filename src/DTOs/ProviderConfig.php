<?php

namespace Accredifysg\SingPassLogin\DTOs;

readonly class ProviderConfig
{
    /**
     * @param  array<int, string>  $availableScopes
     * @param  array<int, string>  $loginScopes
     */
    public function __construct(
        public string $discoveryEndpoint,
        public string $clientId,
        public string $redirectUri,
        public string $domain,
        public string $cacheKey,
        public array $availableScopes,
        public array $loginScopes,
    ) {}

    public static function singPassLogin(): self
    {
        $loginScopes = config('singpass-login.login_scopes', []);

        return new self(
            discoveryEndpoint: config('singpass-login.discovery_endpoint'),
            clientId: config('singpass-login.client_id'),
            redirectUri: config('singpass-login.redirect_uri'),
            domain: config('singpass-login.domain'),
            cacheKey: 'openId:singpass',
            availableScopes: $loginScopes,
            loginScopes: $loginScopes,
        );
    }

    public static function singPassMyInfo(): self
    {
        return new self(
            discoveryEndpoint: config('singpass-login.discovery_endpoint'),
            clientId: config('singpass-login.myinfo_client_id'),
            redirectUri: config('singpass-login.myinfo_redirect_uri'),
            domain: config('singpass-login.domain'),
            cacheKey: 'openId:myinfo',
            availableScopes: config('singpass-login.available_scopes', []),
            loginScopes: config('singpass-login.login_scopes', []),
        );
    }
}
