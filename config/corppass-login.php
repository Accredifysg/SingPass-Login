<?php

use Accredifysg\SingPassLogin\Http\Controllers\CorpPass\LoginCallbackController;
use Accredifysg\SingPassLogin\Http\Controllers\CorpPass\LoginController;
use Accredifysg\SingPassLogin\Listeners\CorpPassSuccessfulLoginListener;

return [
    'client_id' => env('CORPPASS_CLIENT_ID'),
    'redirect_uri' => env('CORPPASS_REDIRECT_URI'),
    'domain' => env('CORPPASS_DOMAIN'),
    'discovery_endpoint' => env('CORPPASS_DISCOVERY_ENDPOINT'),

    // Default routes
    'enable_default_corppass_routes' => env('CORPPASS_USE_DEFAULT_ROUTES', true),

    // CorpPass routes
    'get_authentication_endpoint_url' => env('CORPPASS_AUTHENTICATION_URL', '/ndi/cp/login'),
    'get_authentication_endpoint_controller' => LoginController::class,
    'post_corppass_callback_url' => env('CORPPASS_CALLBACK_URL', '/ndi/cp/callback'),
    'post_corppass_callback_controller' => LoginCallbackController::class,

    // Listener
    'use_default_listener' => env('CORPPASS_USE_DEFAULT_LISTENER', true),
    'listener_class' => CorpPassSuccessfulLoginListener::class,

    /*
    |--------------------------------------------------------------------------
    | Login Scopes
    |--------------------------------------------------------------------------
    |
    | Scopes fulfilled via the ID token claims (sub, sub_attributes, act)
    | and do NOT require a call to the UserInfo endpoint.
    |
    | Any scope NOT in this list triggers a UserInfo endpoint call for
    | authorization data (auth_info, tp_auth_info).
    |
    */
    'login_scopes' => [
        'openid',
        'entity.identity',
        'entity.basic_profile.name',
        'entity.basic_profile.uen_status',
        'user.identity',
        'user.name',
        'user.corppass.email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Scopes
    |--------------------------------------------------------------------------
    |
    | All valid scopes that can be requested during the CorpPass OAuth flow.
    |
    | Login scopes (defined above):
    |   Returned in the ID token's sub_attributes / act.sub_attributes claims.
    |
    | UserInfo scopes:
    |   authinfo  - Authorization info for the entity
    |   tpauthinfo - Third-party authorization info
    |
    | Refer to the official CorpPass documentation for details:
    | https://docs.corppass.gov.sg/technical-specifications/corppass-authorization-api-fapi-2.0/integration-guide
    |
    */
    'available_scopes' => [
        // Core
        'openid',

        // Entity scopes (returned in ID token)
        'entity.identity',
        'entity.basic_profile.name',
        'entity.basic_profile.uen_status',

        // User/Actor scopes (returned in ID token)
        'user.identity',
        'user.name',
        'user.corppass.email',

        // UserInfo scopes (requires UserInfo endpoint call)
        'authinfo',
        'tpauthinfo',
    ],
];
