<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\PushedAuthorizationRequestServiceInterface;
use Accredifysg\SingPassLogin\Support\SingPassLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Random\RandomException;

class FapiAuthenticationService
{
    public function __construct(
        private readonly OpenIdDiscoveryServiceInterface $discoveryService,
        private readonly ScopeValidationService $scopeService,
        private readonly CodeChallengeVerifierService $codeChallengeService,
        private readonly DPoPServiceInterface $dpopService,
        private readonly PushedAuthorizationRequestServiceInterface $parService,
    ) {}

    /**
     * Initiate the FAPI 2.0 authentication flow.
     *
     * @param  array<string, mixed>  $extraParParams  Additional PAR parameters (e.g. authentication_context_type); non-string values are rejected.
     * @return array{redirect_url: string}
     *
     * @throws RandomException
     */
    public function initiateAuth(
        ProviderConfig $config,
        mixed $requestedScopes,
        array $extraParParams = [],
    ): array {
        SingPassLog::info('Initiating auth flow', [
            'client_id' => $config->clientId,
            'redirect_uri' => $config->redirectUri,
            'discovery_endpoint' => $config->discoveryEndpoint,
        ]);

        $this->discoveryService->cacheOpenIdDiscovery($config->discoveryEndpoint, $config->cacheKey);

        $normalizedScopes = self::normalizeRequestedScopes($requestedScopes);
        $normalizedExtraParams = self::normalizeExtraParParams($extraParParams);

        $validatedScopes = $this->scopeService->parseAndValidate($normalizedScopes, $config->availableScopes);
        $scope = $this->scopeService->formatForOAuth($validatedScopes);

        SingPassLog::info('Scopes validated', ['scope' => $scope]);

        $state = Str::uuid()->toString();
        $nonce = Str::uuid()->toString();

        $codeVerifier = $this->codeChallengeService->generateCodeVerifier();
        $codeChallenge = $this->codeChallengeService->generateCodeChallenge($codeVerifier);

        $dpopKey = $this->dpopService->generateKeyPair();

        $openIdConfig = Cache::get($config->cacheKey);

        if (! $openIdConfig instanceof OpenIdConfigurationDto) {
            throw new AuthFlowException(500, 'OpenID configuration not found in cache');
        }

        $dpopProofJwt = $this->dpopService->generateProofJwt($dpopKey, 'POST', $openIdConfig->pushedAuthorizationRequestEndpoint);

        $jwk = JwtService::getSigningJwk();
        $clientAssertion = JwtService::generateClientAssertion($jwk, $config->clientId, $openIdConfig->issuer);

        $parParams = array_merge([
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
            'nonce' => $nonce,
            'client_id' => $config->clientId,
            'redirect_uri' => $config->redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $clientAssertion,
        ], $normalizedExtraParams);

        $requestUri = $this->parService->sendRequest($parParams, $dpopProofJwt, $config->cacheKey);

        session()->put("auth_state_{$state}", true);
        $this->dpopService->storeKeyForState($state, $dpopKey);
        session()->put("code_verifier_{$state}", $codeVerifier);
        session()->put("auth_client_id_{$state}", $config->clientId);
        session()->put("auth_redirect_uri_{$state}", $config->redirectUri);

        $redirectUrl = $openIdConfig->authorizationEndpoint.'?'.http_build_query([
            'client_id' => $config->clientId,
            'request_uri' => $requestUri,
        ]);

        SingPassLog::info('Auth flow initiated', [
            'state' => $state,
            'redirect_url' => $redirectUrl,
            'session_id' => session()->getId(),
        ]);

        return ['redirect_url' => $redirectUrl];
    }

    /**
     * @return string|array<int, string>
     */
    private static function normalizeRequestedScopes(mixed $requestedScopes): string|array
    {
        if (is_string($requestedScopes)) {
            return $requestedScopes;
        }

        if (is_array($requestedScopes)) {
            $scopes = [];
            foreach ($requestedScopes as $item) {
                if (! is_string($item)) {
                    throw new AuthFlowException(400, 'Each requested scope must be a string.');
                }
                $scopes[] = $item;
            }

            return $scopes;
        }

        return 'openid';
    }

    /**
     * @param  array<mixed>  $extraParParams
     * @return array<string, string>
     */
    private static function normalizeExtraParParams(array $extraParParams): array
    {
        $out = [];
        foreach ($extraParParams as $key => $value) {
            if (! is_string($key)) {
                throw new AuthFlowException(400, 'PAR parameter names must be strings.');
            }
            if (is_string($value)) {
                $out[$key] = $value;
            } elseif (is_int($value) || is_float($value)) {
                $out[$key] = (string) $value;
            } elseif (is_bool($value)) {
                $out[$key] = $value ? 'true' : 'false';
            } else {
                throw new AuthFlowException(400, 'PAR parameters must be scalar string-compatible values.');
            }
        }

        return $out;
    }
}
