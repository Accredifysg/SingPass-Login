<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\FapiCallbackResult;
use Accredifysg\SingPassLogin\DTOs\FapiSessionContext;
use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
use Accredifysg\SingPassLogin\Exceptions\AuthenticationErrorException;
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetUserInfoServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\JwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\JwtServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\TokenExchangeServiceInterface;
use Illuminate\Http\Request;

class FapiCallbackService
{
    public function __construct(
        private readonly OpenIdDiscoveryServiceInterface $discoveryService,
        private readonly TokenExchangeServiceInterface $tokenExchangeService,
        private readonly JwtServiceInterface $jwtService,
        private readonly JwksServiceInterface $jwksService,
        private readonly GetUserInfoServiceInterface $userInfoService,
        private readonly DPoPServiceInterface $dpopService,
    ) {}

    /**
     * Validate the callback request and retrieve session context.
     *
     * @throws AuthenticationErrorException
     * @throws AuthFlowException
     */
    public function validateAndRetrieveSession(Request $request): FapiSessionContext
    {
        if ($request->has('error')) {
            throw new AuthenticationErrorException(
                errorCode: $request->input('error'),
                errorDescription: $request->input('error_description'),
            );
        }

        $code = $request->input('code');
        $state = $request->input('state');

        if (! $code || ! $state || ! is_string($state)) {
            throw new AuthFlowException;
        }

        if (! session()->pull("auth_state_{$state}")) {
            throw new AuthFlowException;
        }

        $dpopKey = $this->dpopService->retrieveKeyForState($state);
        $codeVerifier = session()->get("code_verifier_{$state}");
        $clientId = session()->get("auth_client_id_{$state}");
        $redirectUri = session()->get("auth_redirect_uri_{$state}");

        if (! $dpopKey || ! $codeVerifier || ! $clientId || ! $redirectUri) {
            throw new AuthFlowException;
        }

        return new FapiSessionContext(
            code: $code,
            state: $state,
            codeVerifier: $codeVerifier,
            dpopKey: $dpopKey,
            clientId: $clientId,
            redirectUri: $redirectUri,
        );
    }

    /**
     * Process the callback: token exchange, then either UserInfo or ID token path.
     */
    public function processCallback(FapiSessionContext $session, ProviderConfig $config): FapiCallbackResult
    {
        $this->discoveryService->cacheOpenIdDiscovery($config->discoveryEndpoint, $config->cacheKey);

        $tokenResponse = $this->tokenExchangeService->getToken(
            $session->code,
            $session->codeVerifier,
            $session->dpopKey,
            $session->clientId,
            $session->redirectUri,
            $config->cacheKey,
        );

        if ($tokenResponse->hasAccessToken()
            && $this->userInfoService->shouldCallUserInfo($tokenResponse->accessToken, $config->loginScopes)) {
            $userInfoData = $this->userInfoService->getUserInfo(
                $tokenResponse->accessToken,
                $session->dpopKey,
                $config->cacheKey,
            );

            return new FapiCallbackResult(
                idTokenPayload: null,
                userInfoData: $userInfoData,
            );
        }

        $jwtToken = $this->jwtService->jweDecrypt($tokenResponse->idToken);
        $jwksKeyset = $this->jwksService->getJwks($config->cacheKey);
        $payload = $this->jwtService->jwtDecode($jwtToken, $jwksKeyset);
        $this->jwtService->verifyPayload($payload, $config->clientId, $config->domain);

        return new FapiCallbackResult(
            idTokenPayload: $payload,
            userInfoData: null,
        );
    }

    /**
     * Clear all session keys for the given state.
     */
    public function cleanupSession(string $state): void
    {
        $this->dpopService->clearKeyForState($state);
        session()->forget("code_verifier_{$state}");
        session()->forget("auth_client_id_{$state}");
        session()->forget("auth_redirect_uri_{$state}");
        session()->forget("auth_state_{$state}");
    }
}
