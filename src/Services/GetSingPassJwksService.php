<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Exceptions\SingPassJwksException;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWKSet;

final class GetSingPassJwksService implements GetSingPassJwksServiceInterface
{
    /**
     * Handles GET request to the SingPass JWKS Endpoint
     *
     * @throws SingPassJwksException
     */
    public function getSingPassJwks(): JWKSet
    {
        try {
            $response = Http::get(Cache::get('openId')->jwks_uri)->throwUnlessStatus(200)->body();

            return JWKSet::createFromJson($response);
        } catch (Exception) {
            throw new SingPassJwksException;
        }
    }
}
