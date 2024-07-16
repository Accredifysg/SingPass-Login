<?php

namespace Accredifysg\SingPassLogin\Services;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWKSet;

final class getSingPassTokenService
{
    /**
     * Handles the POST Request to SingPass's token endpoint
     *
     * @param $code
     *
     * @return mixed
     *
     * @throws FileNotFoundException
     */
    public static function getToken($code): mixed
    {
        $clientId = config('services.singpass-login.clientId');
        $redirectUrl = config('services.singpass-login.redirectionUrl');
        $grantType = 'authorization_code';
        $clientAssertionType = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

        $jwk = SingPassJwtService::getSigningJwk();
        $clientAssertion = SingPassJwtService::generateClientAssertion($jwk);

        $response = Http::bodyFormat('form_params')
            ->contentType('application/x-www-form-urlencoded; charset=ISO-8859-1')
            ->post(Cache::get('openId')->token_endpoint, [
                'client_assertion_type' => $clientAssertionType,
                'code' => $code,
                'client_id' => $clientId,
                'grant_type' => $grantType,
                'redirect_uri' => $redirectUrl,
                'client_assertion' => $clientAssertion,
            ]);

        try {
            return json_decode($response, false, 512, JSON_THROW_ON_ERROR)->id_token;
        } catch (Exception $e) {
            throw new SingPassTokenException;
        }
    }
}