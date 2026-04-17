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
        $loginScopes = self::stringListFromConfig('singpass-login.login_scopes');

        return new self(
            discoveryEndpoint: self::nonEmptyStringFromConfig('singpass-login.discovery_endpoint'),
            clientId: self::nonEmptyStringFromConfig('singpass-login.client_id'),
            redirectUri: self::nonEmptyStringFromConfig('singpass-login.redirect_uri'),
            domain: self::nonEmptyStringFromConfig('singpass-login.domain'),
            cacheKey: 'openId:singpass',
            availableScopes: $loginScopes,
            loginScopes: $loginScopes,
        );
    }

    public static function corpPass(): self
    {
        return new self(
            discoveryEndpoint: self::nonEmptyStringFromConfig('corppass-login.discovery_endpoint'),
            clientId: self::nonEmptyStringFromConfig('corppass-login.client_id'),
            redirectUri: self::nonEmptyStringFromConfig('corppass-login.redirect_uri'),
            domain: self::nonEmptyStringFromConfig('corppass-login.domain'),
            cacheKey: 'openId:corppass',
            availableScopes: self::stringListFromConfig('corppass-login.available_scopes'),
            loginScopes: self::stringListFromConfig('corppass-login.login_scopes'),
        );
    }

    public static function singPassMyInfo(): self
    {
        return new self(
            discoveryEndpoint: self::nonEmptyStringFromConfig('myinfo.discovery_endpoint'),
            clientId: self::nonEmptyStringFromConfig('myinfo.client_id'),
            redirectUri: self::nonEmptyStringFromConfig('myinfo.redirect_uri'),
            domain: self::nonEmptyStringFromConfig('myinfo.domain'),
            cacheKey: 'openId:myinfo',
            availableScopes: self::stringListFromConfig('myinfo.available_scopes'),
            loginScopes: self::stringListFromConfig('myinfo.login_scopes'),
        );
    }

    /**
     * @throws MissingConfigException
     */
    private static function nonEmptyStringFromConfig(string $key): string
    {
        $value = config($key);

        return is_string($value) && $value !== ''
            ? $value
            : throw new MissingConfigException($key);
    }

    /**
     * @return array<int, string>
     *
     * @throws MissingConfigException
     */
    private static function stringListFromConfig(string $key): array
    {
        $value = config($key, []);
        if ($value === [] || $value === null) {
            return [];
        }
        if (! is_array($value)) {
            throw new MissingConfigException($key);
        }

        $out = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new MissingConfigException($key);
            }
            $out[] = $item;
        }

        return $out;
    }
}
