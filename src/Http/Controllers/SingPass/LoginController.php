<?php

namespace Accredifysg\SingPassLogin\Http\Controllers\SingPass;

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
        $config = ProviderConfig::singPassLogin();

        $extraParams = [];

        $authContextType = $request->query('authentication_context_type')
            ?? config('singpass-login.authentication_context_type');

        if ($authContextType !== null) {
            $extraParams['authentication_context_type'] = $authContextType;
        }

        $authContextMessage = $request->query('authentication_context_message')
            ?? config('singpass-login.authentication_context_message');

        if ($authContextMessage !== null) {
            $extraParams['authentication_context_message'] = $authContextMessage;
        }

        $requestedScopes = $request->query('scopes', 'openid') ?? 'openid';

        $result = $fapiAuth->initiateAuth($config, $requestedScopes, $extraParams);

        return response()->json($result);
    }
}
