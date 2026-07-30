<?php

declare(strict_types=1);

use Accredifysg\SingPassLogin\Http\Controllers\GetJwksEndpointController;

return [
    'signing_kid' => env('NDI_SIGNING_KID'),
    'jwks' => env('NDI_JWKS'),
    'private_jwks' => env('NDI_PRIVATE_JWKS'),

    // FAPI 2.0 / DPoP — ECDSA algorithms supported by this package (must match SingPass expectations).
    'dpop_signing_algorithm' => env('NDI_DPOP_SIGNING_ALGORITHM', 'ES256'), // ES256 | ES384 | ES512

    // Diagnostic logging (logs PAR, token, UserInfo, JWKS and callback requests)
    'enable_logging' => env('NDI_LOGS_ENABLED', false),

    // Where to send the browser when a login/callback fails. When set, failures
    // redirect here with `error` and `error_description` query parameters —
    // suitable for a frontend on another origin. When unset (default), failures
    // redirect to route('login') with session-flashed errors, which requires the
    // host app to define a GET route named `login`.
    'failure_redirect_url' => env('NDI_FAILURE_REDIRECT_URL'),

    // JWKS endpoint
    'get_jwks_endpoint_url' => env('NDI_JWKS_URL', '/ndi/jwks'),
    'get_jwks_endpoint_controller' => GetJwksEndpointController::class,
];
