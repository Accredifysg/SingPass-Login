<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class OpenIdDiscoveryService
{
    /**
     * Calls the SingPass Discovery Endpoint and stores the results in the cache for 1 hour
     *
     * @throws OpenIdDiscoveryException
     */
    public static function cacheOpenIdDiscovery(): void
    {
        Cache::remember('openId', now()->addHour(), static function () {
            $response = Http::get(config('services.singpass-login.discovery_endpoint'))->body();

            try {
                return json_decode($response, false, 512, JSON_THROW_ON_ERROR);
            } catch (Exception) {
                throw new OpenIdDiscoveryException;
            }
        });
    }
}
