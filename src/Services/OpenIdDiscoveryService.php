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
        Cache::remember($cacheKey, now()->addHour(), static function () use ($discoveryEndpoint) {
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

            SingPassLog::info('OpenID Discovery cached', [
                'issuer' => $decoded->issuer ?? null,
                'par_endpoint' => $decoded->pushed_authorization_request_endpoint ?? null,
            ]);

            return OpenIdConfigurationDto::fromDiscoveryResponse($decoded);
        });
    }
}
