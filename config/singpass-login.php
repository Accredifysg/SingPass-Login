<?php

declare(strict_types=1);

use Accredifysg\SingPassLogin\Http\Controllers\SingPass\LoginCallbackController;
use Accredifysg\SingPassLogin\Http\Controllers\SingPass\LoginController;
use Accredifysg\SingPassLogin\Listeners\SingPassSuccessfulLoginListener;

return [
    'client_id' => env('SINGPASS_CLIENT_ID'),
    'redirect_uri' => env('SINGPASS_REDIRECT_URI'),
    'domain' => env('SINGPASS_DOMAIN'),
    'discovery_endpoint' => env('SINGPASS_DISCOVERY_ENDPOINT'),

    // Authentication context (Login apps only)
    'authentication_context_type' => env('SINGPASS_AUTH_CONTEXT_TYPE', 'APP_AUTHENTICATION_DEFAULT'), // Possible values: https://docs.developer.singpass.gov.sg/docs/technical-specifications/integration-guide/1.-authorization-request#possible-authentication_context_type-values
    'authentication_context_message' => env('SINGPASS_AUTH_CONTEXT_MESSAGE'),

    // Default routes
    'enable_default_singpass_routes' => env('SINGPASS_USE_DEFAULT_ROUTES', true),

    // SingPass Login routes
    'get_authentication_endpoint_url' => env('SINGPASS_AUTHENTICATION_URL', '/ndi/sp/login'),
    'get_authentication_endpoint_controller' => LoginController::class,
    'post_singpass_callback_url' => env('SINGPASS_CALLBACK_URL', '/ndi/sp/callback'),
    'post_singpass_callback_controller' => LoginCallbackController::class,

    // Listener
    'use_default_listener' => env('SINGPASS_USE_DEFAULT_LISTENER', true),
    'listener_class' => SingPassSuccessfulLoginListener::class,

    /*
    |--------------------------------------------------------------------------
    | Login Scopes
    |--------------------------------------------------------------------------
    |
    | Scopes that are fulfilled via the ID token (sub_attributes) and do NOT
    | require a call to the UserInfo endpoint.
    |
    */
    'login_scopes' => [
        'openid',
        'user.identity',
        'name',
        'email',
        'mobileno',
    ],
];
