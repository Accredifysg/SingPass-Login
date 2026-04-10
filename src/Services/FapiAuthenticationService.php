<?php

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
     * @param  string|array<int, string>  $requestedScopes
     * @param  array<string, string>  $extraParParams  Additional PAR parameters (e.g. authentication_context_type)
     * @return array{redirect_url: string}
     */
    public function initiateAuth(
        ProviderConfig $config,
        string|array $requestedScopes,
        array $extraParParams = [],
    ): array {
        SingPassLog::info('Initiating auth flow', [
            'client_id' => $config->clientId,
            'redirect_uri' => $config->redirectUri,
            'discovery_endpoint' => $config->discoveryEndpoint,
        ]);

        $this->discoveryService->cacheOpenIdDiscovery($config->discoveryEndpoint, $config->cacheKey);

        $validatedScopes = $this->scopeService->parseAndValidate($requestedScopes, $config->availableScopes);
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
        ], $extraParParams);

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
}
