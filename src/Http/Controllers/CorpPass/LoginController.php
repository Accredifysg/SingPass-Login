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

        $extraParams = [];

        $authContextType = $request->query('authentication_context_type')
            ?? config('corppass-login.authentication_context_type');

        if ($authContextType !== null) {
            $extraParams['authentication_context_type'] = $authContextType;
        }

        $requestedScopes = $request->query('scopes', 'openid') ?? 'openid';

        $result = $fapiAuth->initiateAuth($config, $requestedScopes, $extraParams);

        return response()->json($result);
    }
}
