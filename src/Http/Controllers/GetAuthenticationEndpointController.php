<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\PushedAuthorizationRequestServiceInterface;
use Accredifysg\SingPassLogin\Services\CodeChallengeVerifierService;
use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
use Accredifysg\SingPassLogin\Services\ScopeValidationService;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GetAuthenticationEndpointController extends Controller
{
    /**
     * Returns the authentication endpoint for the browser to consume
     */
    public function __invoke(
        Request $request,
        ScopeValidationService $scopeService,
        CodeChallengeVerifierService $codeChallengeService,
        OpenIdDiscoveryService $discoveryService,
        DPoPServiceInterface $dpopService,
        PushedAuthorizationRequestServiceInterface $parService,
    ): JsonResponse {
        $discoveryService->cacheOpenIdDiscovery();

        // Parse, validate, and normalize scopes
        $requestedScopes = $request->query('scopes', 'openid') ?? 'openid';
        $validatedScopes = $scopeService->parseAndValidate($requestedScopes);
        $scope = $scopeService->formatForOAuth($validatedScopes);

        // Determine whether any MyInfo scopes are present (requires UserInfo endpoint)
        $isMyInfo = $scopeService->hasMyInfoScopes($validatedScopes);

        if (! $isMyInfo) {
            $redirectUri = config('singpass-login.redirect_uri');
            $clientID = config('singpass-login.client_id');
        } else {
            $redirectUri = config('singpass-login.myinfo_redirect_uri');
            $clientID = config('singpass-login.myinfo_client_id');
        }

        $state = Str::uuid();

        $nonce = Str::uuid();

        // PKCE
        $codeVerifier = $codeChallengeService->generateCodeVerifier();
        $codeChallenge = $codeChallengeService->generateCodeChallenge($codeVerifier);

        // DPoP - generate ephemeral key pair
        $dpopKey = $dpopService->generateKeyPair();

        /** @var OpenIdConfigurationDto $openIdConfig */
        $openIdConfig = Cache::get('openId');
        $parEndpoint = $openIdConfig->pushedAuthorizationRequestEndpoint;

        // Generate DPoP proof JWT for the PAR endpoint
        $dpopProofJwt = $dpopService->generateProofJwt($dpopKey, 'POST', $parEndpoint);

        // Generate client assertion
        $jwk = SingPassJwtService::getSigningJwk();
        $clientAssertion = SingPassJwtService::generateClientAssertion($jwk, $clientID);

        // Build PAR request parameters
        $parParams = [
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
            'nonce' => $nonce,
            'client_id' => $clientID,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $clientAssertion,
        ];

        // Add authentication_context_type for Login apps
        if (! $isMyInfo) {
            $authContextType = $request->query('authentication_context_type')
                ?? config('singpass-login.authentication_context_type');

            if ($authContextType !== null) {
                $parParams['authentication_context_type'] = $authContextType;
            }

            $authContextMessage = $request->query('authentication_context_message')
                ?? config('singpass-login.authentication_context_message');

            if ($authContextMessage !== null) {
                $parParams['authentication_context_message'] = $authContextMessage;
            }
        }

        // Send PAR and get request_uri
        $requestUri = $parService->sendRequest($parParams, $dpopProofJwt);

        // Store auth context in session keyed by state (state stored for CSRF verification)
        session()->put("auth_state_{$state}", true);
        $dpopService->storeKeyForState($state, $dpopKey);
        session()->put("code_verifier_{$state}", $codeVerifier);
        session()->put("auth_client_id_{$state}", $clientID);
        session()->put("auth_redirect_uri_{$state}", $redirectUri);

        // Build redirect URL with only client_id and request_uri
        $authorizationEndpoint = $openIdConfig->authorizationEndpoint;
        $redirectUrl = $authorizationEndpoint.'?'.http_build_query([
            'client_id' => $clientID,
            'request_uri' => $requestUri,
        ]);

        return response()->json(['redirect_url' => $redirectUrl]);
    }
}
