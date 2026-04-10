<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
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
            $response = Http::createPendingRequest()->get($discoveryEndpoint);

            if ($response->failed()) {
                throw new OpenIdDiscoveryException($response->status());
            }

            try {
                $decoded = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
            } catch (Exception) {
                throw new OpenIdDiscoveryException(500, 'Open ID Discovery response parse failure.');
            }

            return OpenIdConfigurationDto::fromDiscoveryResponse($decoded);
        });
    }
}
