<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Support\SingPassLog;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class OpenIdDiscoveryService implements OpenIdDiscoveryServiceInterface
{
    /**
     * Calls the provider's Discovery Endpoint and stores the results in the cache for 1 hour.
     *
     * @throws OpenIdDiscoveryException
     */
    public function cacheOpenIdDiscovery(string $discoveryEndpoint, string $cacheKey): void
    {
        // Deliberately not Cache::remember(): a payload left by an older version of
        // this package (a serialized DTO) is non-null but unusable under Laravel 13's
        // restricted unserialize, and remember() would keep returning it until the TTL
        // expired. Hydrating first lets an unreadable entry be replaced immediately.
        if (OpenIdConfigurationDto::fromCache(Cache::get($cacheKey)) instanceof OpenIdConfigurationDto) {
            return;
        }

        Cache::put($cacheKey, $this->fetchOpenIdConfiguration($discoveryEndpoint)->toArray(), now()->addHour());
    }

    /**
     * @throws OpenIdDiscoveryException
     */
    private function fetchOpenIdConfiguration(string $discoveryEndpoint): OpenIdConfigurationDto
    {
        SingPassLog::info('OpenID Discovery request', ['endpoint' => $discoveryEndpoint]);

        $response = Http::createPendingRequest()->get($discoveryEndpoint);

        if ($response->failed()) {
            SingPassLog::error('OpenID Discovery request failed', [
                'endpoint' => $discoveryEndpoint,
                'http_status' => $response->status(),
            ]);

            throw new OpenIdDiscoveryException($response->status());
        }

        try {
            $decoded = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            SingPassLog::error('OpenID Discovery response parse failure', [
                'endpoint' => $discoveryEndpoint,
            ]);

            throw new OpenIdDiscoveryException(500, 'Open ID Discovery response parse failure.');
        }

        if (! is_object($decoded)) {
            throw new OpenIdDiscoveryException(500, 'Open ID Discovery JSON must be an object.');
        }

        $issuer = isset($decoded->issuer) && is_string($decoded->issuer) ? $decoded->issuer : null;
        $parEndpoint = isset($decoded->pushed_authorization_request_endpoint) && is_string($decoded->pushed_authorization_request_endpoint)
            ? $decoded->pushed_authorization_request_endpoint
            : null;

        SingPassLog::info('OpenID Discovery cached', [
            'issuer' => $issuer,
            'par_endpoint' => $parEndpoint,
        ]);

        return OpenIdConfigurationDto::fromDiscoveryResponse($decoded);
    }
}
