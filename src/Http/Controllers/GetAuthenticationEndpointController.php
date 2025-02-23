<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
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
    public function __invoke(Request $request): JsonResponse
    {
        (new OpenIdDiscoveryService)->cacheOpenIdDiscovery();
        $redirectUri = config('singpass-login.redirect_uri');
        $responseType = 'code';
        $state = $request->query('state', 'LOGIN-').Str::uuid();
        $scope = 'openid';
        $singPassAuthenticationEndpoint = Cache::get('openId')->authorization_endpoint;
        $clientID = config('singpass-login.client_id');
        $nonce = Str::uuid();

        $singPassQuery = "redirect_uri=$redirectUri&response_type=$responseType&state=$state&scope=$scope&client_id=$clientID&nonce=$nonce";
        $redirectUrl = "{$singPassAuthenticationEndpoint}?{$singPassQuery}";

        return response()->json(['redirect_url' => $redirectUrl]);
    }
}
