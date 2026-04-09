<?php

namespace Accredifysg\SingPassLogin\Http\Controllers\CorpPass;

use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
use Accredifysg\SingPassLogin\Services\FapiAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LoginController extends Controller
{
    public function __invoke(
        Request $request,
        FapiAuthenticationService $fapiAuth,
    ): JsonResponse {
        $config = ProviderConfig::corpPass();

        $requestedScopes = $request->query('scopes', 'openid') ?? 'openid';

        $result = $fapiAuth->initiateAuth($config, $requestedScopes);

        return response()->json($result);
    }
}
