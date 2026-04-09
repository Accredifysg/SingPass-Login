<?php

use Accredifysg\SingPassLogin\Http\Controllers\GetJwksEndpointController;
use Accredifysg\SingPassLogin\Http\Controllers\SingPass\LoginCallbackController;
use Accredifysg\SingPassLogin\Http\Controllers\SingPass\LoginController;
use Accredifysg\SingPassLogin\Http\Controllers\SingPass\MyInfoCallbackController;
use Accredifysg\SingPassLogin\Http\Controllers\SingPass\MyInfoController;
use Accredifysg\SingPassLogin\Listeners\SingPassSuccessfulLoginListener;

return [
    'client_id' => env('SINGPASS_CLIENT_ID'),
    'redirect_uri' => env('SINGPASS_REDIRECT_URI'),
    'domain' => env('SINGPASS_DOMAIN'),
    'discovery_endpoint' => env('SINGPASS_DISCOVERY_ENDPOINT'),
    'signing_kid' => env('SINGPASS_SIGNING_KID'),
    'jwks' => env('SINGPASS_JWKS'),
    'private_jwks' => env('SINGPASS_PRIVATE_JWKS'),

    // FAPI 2.0 / DPoP
    'dpop_signing_algorithm' => env('SINGPASS_DPOP_SIGNING_ALGORITHM', 'ES256'),

    // Authentication context (Login apps only)
    'authentication_context_type' => env('SINGPASS_AUTH_CONTEXT_TYPE', 'APP_AUTHENTICATION_DEFAULT'), // Possible values: https://docs.developer.singpass.gov.sg/docs/technical-specifications/integration-guide/1.-authorization-request#possible-authentication_context_type-values
    'authentication_context_message' => env('SINGPASS_AUTH_CONTEXT_MESSAGE'),

    // Default routes
    'enable_default_singpass_routes' => env('SINGPASS_USE_DEFAULT_ROUTES', true),
    'enable_default_myinfo_routes' => env('SINGPASS_USE_DEFAULT_MYINFO_ROUTES', true),

    // SingPass Login routes
    'get_authentication_endpoint_url' => env('SINGPASS_AUTHENTICATION_URL', '/ndi/sp/login'),
    'get_authentication_endpoint_controller' => LoginController::class,
    'post_singpass_callback_url' => env('SINGPASS_CALLBACK_URL', '/ndi/sp/callback'),
    'post_singpass_callback_controller' => LoginCallbackController::class,

    // MyInfo routes
    'get_myinfo_authentication_endpoint_url' => env('SINGPASS_MYINFO_AUTHENTICATION_URL', '/ndi/mi/initiate'),
    'get_myinfo_authentication_endpoint_controller' => MyInfoController::class,
    'post_myinfo_callback_url' => env('SINGPASS_MYINFO_CALLBACK_URL', '/ndi/mi/callback'),
    'post_myinfo_callback_controller' => MyInfoCallbackController::class,

    // JWKS endpoint
    'get_jwks_endpoint_url' => env('SINGPASS_JWKS_URL', '/ndi/jwks'),
    'get_jwks_endpoint_controller' => GetJwksEndpointController::class,

    // Listener
    'use_default_listener' => env('SINGPASS_USE_DEFAULT_LISTENER', true),
    'listener_class' => SingPassSuccessfulLoginListener::class,

    // MyInfo
    'myinfo_client_id' => env('SINGPASS_MYINFO_CLIENT_ID'),
    'myinfo_redirect_uri' => env('SINGPASS_MYINFO_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Login Scopes
    |--------------------------------------------------------------------------
    |
    | Scopes that are fulfilled via the ID token (sub_attributes) and do NOT
    | require a call to the UserInfo endpoint. When only these scopes are
    | requested, the Login client_id/redirect_uri are used and the flow
    | emits SingPassSuccessfulLoginEvent.
    |
    | Any scope NOT in this list is considered a MyInfo scope, which triggers
    | a UserInfo endpoint call and emits MyInfoDataRetrievedEvent.
    |
    */
    'login_scopes' => [
        'openid',
        'user.identity',
        'name',
        'email',
        'mobileno',
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Scopes
    |--------------------------------------------------------------------------
    |
    | This array defines all valid scopes that can be requested during
    | the OAuth authorization flow. Scopes are divided into two categories:
    |
    | Login scopes (defined in 'login_scopes' above):
    |   Returned in the ID token's sub_attributes claim. No UserInfo call needed.
    |
    | MyInfo scopes (everything else below):
    |   Returned from the UserInfo endpoint. Requires a MyInfo app configuration.
    |
    | The 'openid' scope is always required as per the OIDC spec.
    |
    | For the complete and up-to-date list of available MyInfo data items,
    | refer to the official MyInfo Data Catalog:
    | https://docs.developer.singpass.gov.sg/docs/data-catalog-myinfo/catalog
    |
    | Common scope categories include:
    | - Personal: uinfin, name, sex, race, nationality, dob, birthcountry, residentialstatus
    | - Contact: email, mobileno, regadd (registered address)
    | - Family: marital, marriagecertno, countryofmarriage, childrenbirthrecords
    | - Financial: cpfcontributions, cpfbalances, cpfemployers
    | - Education: edulevel, gradyear, schoolname
    | - Employment: employment, occupation, workpassstatus, passtype, passstatus
    | - Vehicle: vehicles, drivinglicence
    | - Property: housingtype, hdbtype, ownerprivate
    |
    | Example usage:
    | GET /sp/login?scopes=openid,name,email,mobileno
    |
    */
    'available_scopes' => [
        // Core authentication scope (always required)
        'openid',

        // FAPI 2.0 Login scopes (returned in ID token)
        'user.identity',
        'name',
        'email',
        'mobileno',

        // Personal Information (MyInfo - requires UserInfo endpoint)
        'uinfin',
        'partialuinfin',
        'aliasname',
        'hanyupinyinname',
        'hanyupinyinaliasname',
        'marriedname',
        'sex',
        'race',
        'secondaryrace',
        'dialect',
        'dob',
        'residentialstatus',
        'nationality',
        'birthcountry',
        'passportnumber',
        'passportexpirydate',
        'passtype',
        'passstatus',
        'passexpirydate',
        'employmentsector',

        // Contact Information
        'regadd',
        'mailadd',
        'billadd',

        // Housing Information (based on registered address)
        'hdbtype',
        'housingtype',

        // Family Information
        'marital',
        'marriagecertno',
        'countryofmarriage',
        'marriagedate',
        'divorcedate',
        'childrenbirthrecords',
        'sponsoredchildrenrecords',

        // Financial Information
        'cpfcontributions',
        'cpfbalances',
        'cpfemployers',
        'cpfhousingwithdrawal',

        // Education Information
        'edulevel',
        'gradyear',
        'schoolname',

        // Employment Information
        'employment',
        'occupation',
        'workpassstatus',
        'workpassexpirydate',

        // Vehicle Information
        'vehicles',
        'drivinglicence',

        // Property Ownership
        'ownerprivate',

        // Government Schemes
        'gstvoucher',
        'merdekagen',
        'pioneergen',
        'silversupport',
    ],
];
