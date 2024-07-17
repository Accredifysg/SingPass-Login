<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Exceptions\SingPassTokenException;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class GetSingPassTokenService
{
    /**
     * Handles the POST Request to SingPass's token endpoint
     *
     * @throws ConnectionException
     */
    public static function getToken(string $code): mixed
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
        } catch (Exception) {
            throw new SingPassTokenException;
        }
    }
}
