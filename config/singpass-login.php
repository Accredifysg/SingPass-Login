<?php

use Accredifysg\SingPassLogin\Http\Controllers\GetAuthenticationEndpointController;
use Accredifysg\SingPassLogin\Http\Controllers\GetJwksEndpointController;
use Accredifysg\SingPassLogin\Http\Controllers\PostSingPassCallbackController;
use Accredifysg\SingPassLogin\Listeners\SingPassSuccessfulLoginListener;

return [
    'client_id' => env('SINGPASS_CLIENT_ID'),
    'redirect_uri' => env('SINGPASS_REDIRECT_URI'),
    'domain' => env('SINGPASS_DOMAIN'),
    'discovery_endpoint' => env('SINGPASS_DISCOVERY_ENDPOINT'),
    'signing_kid' => env('SINGPASS_SIGNING_KID'),
    'jwks' => env('SINGPASS_JWKS'),
    'private_jwks' => env('SINGPASS_PRIVATE_JWKS'),

    // Default routes
    'enable_default_singpass_routes' => env('SINGPASS_USE_DEFAULT_ROUTES', true),
    'get_jwks_endpoint_url' => env('SINGPASS_JWKS_URL', '/sp/jwks'),
    'get_authentication_endpoint_url' => env('SINGPASS_AUTHENTICATION_URL', '/sp/login'),
    'post_singpass_callback_url' => env('SINGPASS_CALLBACK_URL', '/sp/callback'),

    // Default controllers
    'get_jwks_endpoint_controller' => GetJwksEndpointController::class,
    'get_authentication_endpoint_controller' => GetAuthenticationEndpointController::class,
    'post_singpass_callback_controller' => PostSingPassCallbackController::class,

    // Debug mode
    'debug_mode' => env('SINGPASS_DEBUG_MODE', false),

    // Listener
    'use_default_listener' => env('SINGPASS_USE_DEFAULT_LISTENER', true),
    'listener_class' => SingPassSuccessfulLoginListener::class,
];
