<?php

use Accredifysg\SingPassLogin\Http\Controllers\GetJwksEndpointController;
use Accredifysg\SingPassLogin\Http\Controllers\PostSingPassCallbackController;

return [
    'client_id' => env('SINGPASS_CLIENT_ID'),
    'client_secret' => env('SINGPASS_CLIENT_SECRET'),
    'redirect_uri' => env('SINGPASS_REDIRECT_URI'),
    'script_source' => env('SINGPASS_SCRIPT_SOURCE'),
    'domain' => env('SINGPASS_DOMAIN'),
    'discovery_endpoint' => env('SINGPASS_DISCOVERY_ENDPOINT'),
    'signing_kid' => env('SINGPASS_SIGNING_KID'),
    'private_exponent' => env('SINGPASS_PRIVATE_EXPONENT'),
    'encryption_key' => env('SINGPASS_ENCRYPTION_KEY'),

    // Default routes
    'enable_default_singpass_routes' => env('SINGPASS_USE_DEFAULT_ROUTES', true),

    'get_jwks_endpoint_url' => env('SINGPASS_JWKS_URL', '/sp/jwks'),
    'post_singpass_callback_url' => env('SINGPASS_CALLBACK_URL', '/sp/callback'),

    'get_jwks_endpoint_controller' => GetJwksEndpointController::class,
    'post_singpass_callback_controller' => PostSingPassCallbackController::class,

    // Debug mode
    'debug_mode' => env('SINGPASS_DEBUG_MODE', false),
];
