<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Accredifysg\SingPassLogin\Exceptions\TokenExchangeException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\TokenExchangeServiceInterface;
use Accredifysg\SingPassLogin\Support\SingPassLog;
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

        SingPassLog::info('Token exchange request', [
            'endpoint' => $tokenEndpoint,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ]);

        $response = Http::asForm()
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
            SingPassLog::error('Token exchange response parse failure', [
                'endpoint' => $tokenEndpoint,
                'http_status' => $response->status(),
            ]);

            throw new TokenExchangeException(
                $response->status(),
                'Failed to parse token endpoint response',
            );
        }

        if ($response->failed() || isset($responseData->error)) {
            $errorCode = $responseData->error ?? 'server_error';
            $errorDescription = $responseData->error_description ?? 'Token exchange request failed';

            SingPassLog::error('Token exchange failed', [
                'endpoint' => $tokenEndpoint,
                'http_status' => $response->status(),
                'error' => $errorCode,
                'error_description' => $errorDescription,
            ]);

            throw new TokenExchangeException(
                $response->status(),
                "{$errorCode}: {$errorDescription}",
            );
        }

        if (! isset($responseData->id_token)) {
            SingPassLog::error('Token response missing id_token', [
                'endpoint' => $tokenEndpoint,
            ]);

            throw new TokenExchangeException(500, 'Token response missing id_token');
        }

        SingPassLog::info('Token exchange successful', [
            'endpoint' => $tokenEndpoint,
            'has_access_token' => isset($responseData->access_token),
        ]);

        return new TokenResponseDto(
            idToken: $responseData->id_token,
            accessToken: $responseData->access_token ?? null,
        );
    }
}
