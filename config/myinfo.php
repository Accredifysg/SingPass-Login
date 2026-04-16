<?php

declare(strict_types=1);

use Accredifysg\SingPassLogin\Http\Controllers\SingPass\MyInfoCallbackController;
use Accredifysg\SingPassLogin\Http\Controllers\SingPass\MyInfoController;

return [
    'client_id' => env('MYINFO_CLIENT_ID'),
    'redirect_uri' => env('MYINFO_REDIRECT_URI'),
    'discovery_endpoint' => env('MYINFO_DISCOVERY_ENDPOINT'),
    'domain' => env('MYINFO_DOMAIN'),

    // Default routes
    'enable_default_myinfo_routes' => env('MYINFO_USE_DEFAULT_ROUTES', true),

    // MyInfo routes
    'get_myinfo_authentication_endpoint_url' => env('MYINFO_AUTHENTICATION_URL', '/ndi/mi/initiate'),
    'get_myinfo_authentication_endpoint_controller' => MyInfoController::class,
    'post_myinfo_callback_url' => env('MYINFO_CALLBACK_URL', '/ndi/mi/callback'),
    'post_myinfo_callback_controller' => MyInfoCallbackController::class,

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
