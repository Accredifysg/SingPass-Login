<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Accredifysg\SingPassLogin\Exceptions\TokenExchangeException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\TokenExchangeServiceInterface;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWK;

final class TokenExchangeService implements TokenExchangeServiceInterface
{
    public function __construct(
        private readonly DPoPServiceInterface $dpopService
    ) {}

    /**
     * Handles the POST Request to the provider's token endpoint.
     *
     * @throws ConnectionException
     */
    public function getToken(string $code, string $codeVerifier, JWK $dpopKey, string $clientId, string $redirectUri, string $cacheKey): TokenResponseDto
    {
        $jwk = JwtService::getSigningJwk();

        $openIdConfig = Cache::get($cacheKey);

        if (! $openIdConfig instanceof OpenIdConfigurationDto) {
            throw new TokenExchangeException(500, 'OpenID configuration not found in cache');
        }

        $tokenEndpoint = $openIdConfig->tokenEndpoint;

        $clientAssertion = JwtService::generateClientAssertion($jwk, $clientId, $openIdConfig->issuer);

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
            $responseData = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            throw new TokenExchangeException(
                $response->status(),
                'Failed to parse token endpoint response',
            );
        }

        if ($response->failed() || isset($responseData->error)) {
            $errorCode = $responseData->error ?? 'server_error';
            $errorDescription = $responseData->error_description ?? 'Token exchange request failed';
            throw new TokenExchangeException(
                $response->status(),
                "{$errorCode}: {$errorDescription}",
            );
        }

        if (! isset($responseData->id_token)) {
            throw new TokenExchangeException(500, 'Token response missing id_token');
        }

        return new TokenResponseDto(
            idToken: $responseData->id_token,
            accessToken: $responseData->access_token ?? null,
        );
    }
}
