<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Accredifysg\SingPassLogin\Exceptions\SingPassTokenException;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassTokenServiceInterface;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class GetSingPassTokenService implements GetSingPassTokenServiceInterface
{
    /**
     * Handles the POST Request to SingPass's token endpoint
     *
     * @throws ConnectionException
     */
    public function getToken(string $code, string $codeVerifier, string $state): TokenResponseDto
    {
        if (str_starts_with($state, 'MYINFO-')) {
            $clientId = config('singpass-login.myinfo_client_id');
            $redirectUrl = config('singpass-login.myinfo_redirect_uri');
        } else {
            $clientId = config('singpass-login.client_id');
            $redirectUrl = config('singpass-login.redirect_uri');
        }

        $grantType = 'authorization_code';
        $clientAssertionType = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

        $jwk = SingPassJwtService::getSigningJwk();
        $clientAssertion = SingPassJwtService::generateClientAssertion($jwk, $code, $clientId);

        $response = Http::bodyFormat('form_params')
            ->contentType('application/x-www-form-urlencoded; charset=ISO-8859-1')
            ->post(Cache::get('openId')->token_endpoint, [
                'client_assertion_type' => $clientAssertionType,
                'code' => $code,
                'client_id' => $clientId,
                'grant_type' => $grantType,
                'redirect_uri' => $redirectUrl,
                'client_assertion' => $clientAssertion,
                'code_verifier' => $codeVerifier,
            ]);

        try {
            $responseData = json_decode($response, false, 512, JSON_THROW_ON_ERROR);

            return new TokenResponseDto(
                idToken: $responseData->id_token,
                accessToken: $responseData->access_token ?? null
            );
        } catch (Exception) {
            throw new SingPassTokenException;
        }
    }
}
