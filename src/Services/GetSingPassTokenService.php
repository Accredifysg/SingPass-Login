<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Accredifysg\SingPassLogin\Exceptions\SingPassTokenException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassTokenServiceInterface;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWK;

final class GetSingPassTokenService implements GetSingPassTokenServiceInterface
{
    public function __construct(
        private readonly DPoPServiceInterface $dpopService
    ) {}

    /**
     * Handles the POST Request to SingPass's token endpoint
     *
     * @throws ConnectionException
     */
    public function getToken(string $code, string $codeVerifier, JWK $dpopKey, string $clientId, string $redirectUri): TokenResponseDto
    {
        $jwk = SingPassJwtService::getSigningJwk();
        $clientAssertion = SingPassJwtService::generateClientAssertion($jwk, $clientId);

        /** @var OpenIdConfigurationDto $openIdConfig */
        $openIdConfig = Cache::get('openId');
        $tokenEndpoint = $openIdConfig->tokenEndpoint;

        // Generate DPoP proof JWT for the token endpoint
        $dpopProofJwt = $this->dpopService->generateProofJwt($dpopKey, 'POST', $tokenEndpoint);

        $response = Http::bodyFormat('form_params')
            ->contentType('application/x-www-form-urlencoded; charset=ISO-8859-1')
            ->withHeaders(['DPoP' => $dpopProofJwt])
            ->post($tokenEndpoint, [
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'code' => $code,
                'client_id' => $clientId,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
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
