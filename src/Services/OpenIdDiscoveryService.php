<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class OpenIdDiscoveryService implements OpenIdDiscoveryServiceInterface
{
    /**
     * Calls the SingPass Discovery Endpoint and stores the results in the cache for 1 hour
     *
     * @throws OpenIdDiscoveryException
     */
    public function cacheOpenIdDiscovery(): void
    {
        Cache::remember('openId', now()->addHour(), static function () {
            $response = Http::get(config('services.singpass-login.discovery_endpoint'));

            if ($response->failed()) {
                throw new OpenIdDiscoveryException($response->status());
            }

            try {
                return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
            } catch (Exception) {
                throw new OpenIdDiscoveryException(500, 'Open ID Discovery response parse failure.');
            }
        });
    }
}
