<?php

use Accredifysg\SingPassLogin\Http\Controllers\GetJwksEndpointController;

return [
    'signing_kid' => env('NDI_SIGNING_KID'),
    'jwks' => env('NDI_JWKS'),
    'private_jwks' => env('NDI_PRIVATE_JWKS'),

    // FAPI 2.0 / DPoP — ECDSA algorithms supported by this package (must match SingPass expectations).
    'dpop_signing_algorithm' => env('NDI_DPOP_SIGNING_ALGORITHM', 'ES256'), // ES256 | ES384 | ES512

    // Diagnostic logging (logs PAR, token, UserInfo, JWKS and callback requests)
    'enable_logging' => env('NDI_LOGS_ENABLED', false),

    // JWKS endpoint
    'get_jwks_endpoint_url' => env('NDI_JWKS_URL', '/ndi/jwks'),
    'get_jwks_endpoint_controller' => GetJwksEndpointController::class,
];
