<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\DTOs;

use Accredifysg\SingPassLogin\Exceptions\MissingConfigException;

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
        $required = [
            'singpass-login.discovery_endpoint',
            'singpass-login.client_id',
            'singpass-login.redirect_uri',
            'singpass-login.domain',
        ];
        self::validateRequired($required);

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

    public static function corpPass(): self
    {
        $required = [
            'corppass-login.discovery_endpoint',
            'corppass-login.client_id',
            'corppass-login.redirect_uri',
            'corppass-login.domain',
        ];
        self::validateRequired($required);

        return new self(
            discoveryEndpoint: config('corppass-login.discovery_endpoint'),
            clientId: config('corppass-login.client_id'),
            redirectUri: config('corppass-login.redirect_uri'),
            domain: config('corppass-login.domain'),
            cacheKey: 'openId:corppass',
            availableScopes: config('corppass-login.available_scopes', []),
            loginScopes: config('corppass-login.login_scopes', []),
        );
    }

    public static function singPassMyInfo(): self
    {
        $required = [
            'myinfo.discovery_endpoint',
            'myinfo.client_id',
            'myinfo.redirect_uri',
            'myinfo.domain',
        ];
        self::validateRequired($required);

        return new self(
            discoveryEndpoint: config('myinfo.discovery_endpoint'),
            clientId: config('myinfo.client_id'),
            redirectUri: config('myinfo.redirect_uri'),
            domain: config('myinfo.domain'),
            cacheKey: 'openId:myinfo',
            availableScopes: config('myinfo.available_scopes', []),
            loginScopes: config('myinfo.login_scopes', []),
        );
    }

    /**
     * @param  array<int, string>  $keys
     *
     * @throws MissingConfigException
     */
    private static function validateRequired(array $keys): void
    {
        foreach ($keys as $key) {
            if (config($key) === null) {
                throw new MissingConfigException($key);
            }
        }
    }
}
