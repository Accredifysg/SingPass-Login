<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Http\Controllers\SingPass;

use Accredifysg\SingPassLogin\DTOs\ProviderConfig;
use Accredifysg\SingPassLogin\Services\FapiAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MyInfoController extends Controller
{
    public function __invoke(
        Request $request,
        FapiAuthenticationService $fapiAuth,
    ): JsonResponse {
        $config = ProviderConfig::singPassMyInfo();

        $requestedScopes = $request->query('scopes', 'openid') ?? 'openid';

        $result = $fapiAuth->initiateAuth($config, $requestedScopes);

        return response()->json($result);
    }
}
