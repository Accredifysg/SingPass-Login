<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\Services\CodeChallengeVerifierService;
use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
use Accredifysg\SingPassLogin\Services\ScopeValidationService;
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
        OpenIdDiscoveryService $discoveryService
    ): JsonResponse {
        $discoveryService->cacheOpenIdDiscovery();
        $redirectUri = config('singpass-login.redirect_uri');
        $responseType = 'code';
        $statePrefix = $request->query('state', 'LOGIN-');
        $state = (is_string($statePrefix) ? $statePrefix : 'LOGIN-').Str::uuid();

        // Parse, validate, and normalize scopes
        $requestedScopes = $request->query('scopes', 'openid') ?? 'openid';
        $validatedScopes = $scopeService->parseAndValidate($requestedScopes);
        $scope = $scopeService->formatForOAuth($validatedScopes);

        $singPassAuthenticationEndpoint = Cache::get('openId')->authorization_endpoint;
        $clientID = config('singpass-login.client_id');
        $nonce = Str::uuid();

        // PKCE
        $codeChallengeMethod = 'S256';
        $codeVerifier = $codeChallengeService->generateCodeVerifier();
        $codeChallenge = $codeChallengeService->generateCodeChallenge($codeVerifier);

        $singPassQuery = "redirect_uri=$redirectUri&response_type=$responseType&state=$state&scope=$scope&client_id=$clientID&nonce=$nonce&code_challenge_method=$codeChallengeMethod&code_challenge=$codeChallenge";
        $redirectUrl = "{$singPassAuthenticationEndpoint}?{$singPassQuery}";

        return response()->json(['redirect_url' => $redirectUrl])->cookie('code_verifier', $codeVerifier);
    }
}
