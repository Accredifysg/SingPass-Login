<?php

use Accredifysg\SingPassLogin\Http\Controllers\GetAuthenticationEndpointController;
use Accredifysg\SingPassLogin\Http\Controllers\GetJwksEndpointController;
use Accredifysg\SingPassLogin\Http\Controllers\GetSingPassCallbackController;
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
    'post_singpass_callback_controller' => GetSingPassCallbackController::class,

    // Listener
    'use_default_listener' => env('SINGPASS_USE_DEFAULT_LISTENER', true),
    'listener_class' => SingPassSuccessfulLoginListener::class,

    // MyInfo
    'myinfo_client_id' => env('SINGPASS_MYINFO_CLIENT_ID'),
    'myinfo_redirect_uri' => env('SINGPASS_MYINFO_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Available MyInfo Scopes
    |--------------------------------------------------------------------------
    |
    | This array defines the valid MyInfo scopes that can be requested during
    | the OAuth authorization flow. These scopes determine what user data can
    | be retrieved from the MyInfo UserInfo endpoint.
    |
    | The 'openid' scope is always required and allowed for authentication.
    | Additional scopes enable retrieval of specific user data categories.
    |
    | Scope validation occurs when scopes are passed as query parameters to
    | the authentication endpoint. Invalid scopes will be filtered out and
    | logged as warnings.
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

        // Personal Information
        'uinfin',
        'partialuinfin',
        'name',
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
        'mobileno',
        'email',
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
