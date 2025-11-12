<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\Services\CodeChallengeVerifierService;
use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GetAuthenticationEndpointController extends Controller
{
    /**
     * Returns the authentication endpoint for the browser to consume
     */
    public function __invoke(Request $request): JsonResponse
    {
        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery();
        $redirectUri = config('singpass-login.redirect_uri');
        $responseType = 'code';
        $state = $request->query('state', 'LOGIN-').Str::uuid();

        // Get scopes from query parameter, default to ['openid']
        $requestedScopes = $request->query('scopes', 'openid');
        $scopesArray = is_array($requestedScopes)
            ? $requestedScopes
            : explode(',', $requestedScopes);

        // Validate scopes
        $validatedScopes = $this->validateScopes($scopesArray);

        // Ensure openid is always included
        if (! in_array('openid', $validatedScopes)) {
            array_unshift($validatedScopes, 'openid');
        }

        // Join scopes with spaces for OAuth 2.0 authorization URL
        $scope = implode(' ', $validatedScopes);

        $singPassAuthenticationEndpoint = Cache::get('openId')->authorization_endpoint;
        $clientID = config('singpass-login.client_id');
        $nonce = Str::uuid();

        // PKCE
        $codeChallengeMethod = 'S256';
        $codeChallengeVerifierService = new CodeChallengeVerifierService;
        $codeVerifier = $codeChallengeVerifierService->generateCodeVerifier();
        $codeChallenge = $codeChallengeVerifierService->generateCodeChallenge($codeVerifier);

        $singPassQuery = "redirect_uri=$redirectUri&response_type=$responseType&state=$state&scope=$scope&client_id=$clientID&nonce=$nonce&code_challenge_method=$codeChallengeMethod&code_challenge=$codeChallenge";
        $redirectUrl = "{$singPassAuthenticationEndpoint}?{$singPassQuery}";

        return response()->json(['redirect_url' => $redirectUrl])->cookie('code_verifier', $codeVerifier);
    }

    /**
     * Validate requested scopes against available scopes configuration
     *
     * @param  array<int, string>  $scopes
     * @return array<int, string>
     */
    private function validateScopes(array $scopes): array
    {
        $availableScopes = config('singpass-login.available_scopes', []);

        // Always allow 'openid'
        if (! in_array('openid', $availableScopes)) {
            $availableScopes[] = 'openid';
        }

        return array_values(array_filter($scopes, function ($scope) use ($availableScopes) {
            $isValid = in_array($scope, $availableScopes);
            if (! $isValid) {
                Log::warning("Invalid scope requested: {$scope}");
            }

            return $isValid;
        }));
    }
}
