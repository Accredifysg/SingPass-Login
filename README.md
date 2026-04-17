# SingPass-Login

![badge.svg](coverage/badge.svg) ![](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg?style=flat)

PHP Laravel Package for **SingPass Login**, **MyInfo**, and **CorpPass**. The authorization flow follows **FAPI 2.0–style** integration: **Pushed Authorization Requests (PAR)** with **DPoP** on the PAR, token, and UserInfo calls, **PKCE**, and private-key **JWT client assertions**. Your OpenID Provider metadata (discovery) must expose a `pushed_authorization_request_endpoint`; the package validates this when caching discovery.

<a href="https://api.singpass.gov.sg/library/login/developers/overview-at-a-glance" rel="noreferrer nofollow">Official SingPass Login Docs</a> · <a href="https://docs.corppass.gov.sg/technical-specifications/corppass-authorization-api-fapi-2.0/integration-guide" rel="noreferrer nofollow">Official CorpPass Docs</a>

## Architecture

The package separates **shared FAPI 2.0 choreography** from **provider-specific logic**:

- **Shared layer** (`FapiAuthenticationService`, `FapiCallbackService`) handles discovery, PAR, DPoP, PKCE, token exchange, and JWE/JWS processing.
- **Thin controllers** for each flow (SingPass Login, MyInfo, CorpPass) delegate to the shared layer and fire provider-specific events.
- A `ProviderConfig` DTO encapsulates per-provider configuration (client ID, redirect URI, cache key, scopes).

```
┌─────────────────────────────────────────────────────────────────┐
│                  Shared FAPI 2.0 Services                       │
│  FapiAuthenticationService · FapiCallbackService · DPoPService  │
│  PARService · TokenExchangeService · JwtService · JwksService   │
│  OpenIdDiscoveryService · GetUserInfoService · PKCEService      │
└──────────┬──────────────────┬──────────────────┬────────────────┘
           │                  │                  │
    ┌──────┴──────┐   ┌──────┴──────┐   ┌──────┴──────┐
    │  SingPass   │   │   MyInfo    │   │  CorpPass   │
    │  Login +    │   │  Login +    │   │  Login +    │
    │  Callback   │   │  Callback   │   │  Callback   │
    └─────────────┘   └─────────────┘   └─────────────┘
```

## Installation

You can install the package via composer:

```bash
composer require accredifysg/singpass-login
```

Publish the config files:

```bash
php artisan vendor:publish --provider="Accredifysg\SingPassLogin\SingPassLoginServiceProvider" --tag="config"
```

This publishes four config files:

| File | Purpose |
|---|---|
| `config/ndi.php` | Shared NDI infrastructure (JWKS, signing, DPoP, logging) |
| `config/singpass-login.php` | SingPass Login credentials, routes, listener |
| `config/myinfo.php` | MyInfo credentials, routes, scopes |
| `config/corppass-login.php` | CorpPass credentials, routes, listener, scopes |

Optionally, publish the default listener that logs in a user on `SingPassSuccessfulLoginEvent`:

```bash
php artisan vendor:publish --provider="Accredifysg\SingPassLogin\SingPassLoginServiceProvider" --tag="listener"
```

## Configuration

### Shared NDI (`config/ndi.php`)

JWKS, signing keys, DPoP algorithm, and logging — shared across all providers.

```.dotenv
NDI_SIGNING_KID=
NDI_JWKS=
NDI_PRIVATE_JWKS=

# FAPI 2.0 / DPoP — ephemeral key algorithm for DPoP proofs (ES256, ES384, or ES512; default ES256)
NDI_DPOP_SIGNING_ALGORITHM=ES256

# Diagnostic logging (disabled by default)
NDI_LOGS_ENABLED=false
```

### SingPass Login (`config/singpass-login.php`)

```.dotenv
SINGPASS_CLIENT_ID=
SINGPASS_REDIRECT_URI=
# Must match the `issuer` from the FAPI discovery endpoint, e.g.
# Staging: https://stg-id.singpass.gov.sg/fapi
# Production: https://id.singpass.gov.sg/fapi
SINGPASS_DOMAIN=
# Must be the FAPI discovery URL (includes pushed_authorization_request_endpoint), e.g.
# Staging: https://stg-id.singpass.gov.sg/fapi/.well-known/openid-configuration
# Production: https://id.singpass.gov.sg/fapi/.well-known/openid-configuration
SINGPASS_DISCOVERY_ENDPOINT=

# Login app authentication context (see SingPass integration guide)
SINGPASS_AUTH_CONTEXT_TYPE=APP_AUTHENTICATION_DEFAULT
# SINGPASS_AUTH_CONTEXT_MESSAGE=

# Default Listener
SINGPASS_USE_DEFAULT_LISTENER=true
```

### MyInfo (`config/myinfo.php`)

```.dotenv
MYINFO_CLIENT_ID=
MYINFO_REDIRECT_URI=
MYINFO_DISCOVERY_ENDPOINT=
MYINFO_DOMAIN=
```

### CorpPass (`config/corppass-login.php`)

```.dotenv
CORPPASS_CLIENT_ID=
CORPPASS_REDIRECT_URI=
CORPPASS_DOMAIN=
CORPPASS_DISCOVERY_ENDPOINT=

# Default Listener (disabled by default)
CORPPASS_USE_DEFAULT_LISTENER=false

# Login app authentication context
CORPPASS_AUTH_CONTEXT_TYPE=APP_AUTHENTICATION_DEFAULT
```

### Enabling / Disabling Modules

Each flow can be independently toggled via environment variables. All are enabled by default.

| Variable | Config file | Default | Controls |
|---|---|---|---|
| `SINGPASS_USE_DEFAULT_ROUTES` | `singpass-login.php` | `true` | SingPass Login routes |
| `MYINFO_USE_DEFAULT_ROUTES` | `myinfo.php` | `true` | MyInfo routes |
| `CORPPASS_USE_DEFAULT_ROUTES` | `corppass-login.php` | `true` | CorpPass routes |

The JWKS endpoint (`/ndi/jwks`) is always registered regardless of these flags, as it is shared across all providers.

Route URLs are also configurable:

```.dotenv
SINGPASS_AUTHENTICATION_URL=/ndi/sp/login
SINGPASS_CALLBACK_URL=/ndi/sp/callback
MYINFO_AUTHENTICATION_URL=/ndi/mi/initiate
MYINFO_CALLBACK_URL=/ndi/mi/callback
NDI_JWKS_URL=/ndi/jwks
CORPPASS_AUTHENTICATION_URL=/ndi/cp/login
CORPPASS_CALLBACK_URL=/ndi/cp/callback
```

## Routes

The package registers the following routes under the `web` middleware group:

| Route | Controller | Name | Purpose |
|---|---|---|---|
| `GET /ndi/jwks` | `GetJwksEndpointController` | `singpass.jwks` | Expose your application's JWKS (always active) |
| `GET /ndi/sp/login` | `SingPass\LoginController` | `singpass.login` | Initiate SingPass Login |
| `GET /ndi/sp/callback` | `SingPass\LoginCallbackController` | `singpass.callback` | Handle SingPass Login callback |
| `GET /ndi/mi/initiate` | `MyInfo\MyInfoController` | `myinfo.login` | Initiate MyInfo flow |
| `GET /ndi/mi/callback` | `MyInfo\MyInfoCallbackController` | `myinfo.callback` | Handle MyInfo callback |
| `GET /ndi/cp/login` | `CorpPass\LoginController` | `corppass.login` | Initiate CorpPass Login |
| `GET /ndi/cp/callback` | `CorpPass\LoginCallbackController` | `corppass.callback` | Handle CorpPass callback |

Each auth controller returns **JSON** with a `redirect_url` the browser should navigate to. The callback controllers handle the OAuth redirect, validate `state` (CSRF), exchange the code using DPoP, and fire the appropriate event.

If you prefer custom controllers, override the `*_controller` keys in the respective config file.

## SingPass Login

### Starting a Login

`GET /ndi/sp/login` returns `200` JSON: `{ "redirect_url": "..." }`. The browser (or SPA) should request that URL with **same-origin credentials** so the session cookie is sent, then navigate to `redirect_url`.

Optional query parameters: `authentication_context_type` and `authentication_context_message` override config defaults for that request. See the [SingPass authorization request documentation](https://docs.developer.singpass.gov.sg/docs/technical-specifications/integration-guide/1.-authorization-request#possible-authentication_context_type-values) for valid values.

```javascript
async function startSingPassLogin(scopes) {
  const qs = new URLSearchParams({ scopes: scopes.join(',') });
  const res = await fetch(`/ndi/sp/login?${qs}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) throw new Error('Login bootstrap failed');
  const { redirect_url } = await res.json();
  window.location.assign(redirect_url);
}

// Login scopes only (data returned in ID token)
await startSingPassLogin(['openid', 'name', 'email', 'mobileno']);
```

### Listener

If you published the default listener, edit it to map your user retrieval via NRIC. Read the NRIC/FIN from the readonly **`nric`** property (populated from `sub_attributes.identity_number` when the **`user.identity`** scope is requested). **`SingPassUser::getNric()` is deprecated** and will be removed in a future major release; migrate listeners to `$singPassUser->nric`.

```php
public function handle(SingPassSuccessfulLoginEvent $event): void
{
    $singPassUser = $event->getSingPassUser();
    $nric = $singPassUser->nric;

    if (! $nric) {
        // NRIC is only available when the 'user.identity' scope is requested.
        throw new SingPassLoginException;
    }

    $user = User::where('nric', '=', $nric)->first();

    if (! $user) {
        throw new SingPassLoginException;
    }

    Auth::login($user);
}
```

If you prefer a custom listener, set `SINGPASS_USE_DEFAULT_LISTENER=false` and replace `listener_class` in `singpass-login.php`.

## MyInfo Integration

MyInfo has its own dedicated routes (`/ndi/mi/initiate` and `/ndi/mi/callback`), config file (`config/myinfo.php`), and separate client credentials (`MYINFO_CLIENT_ID` / `MYINFO_REDIRECT_URI`).

### Starting a MyInfo Flow

```javascript
async function startMyInfo(scopes) {
  const qs = new URLSearchParams({ scopes: scopes.join(',') });
  const res = await fetch(`/ndi/mi/initiate?${qs}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) throw new Error('MyInfo bootstrap failed');
  const { redirect_url } = await res.json();
  window.location.assign(redirect_url);
}

await startMyInfo(['openid', 'name', 'email', 'mobileno', 'nationality', 'dob']);
```

### How It Works

The MyInfo callback controller calls the UserInfo endpoint (with DPoP) to retrieve the requested data and emits `MyInfoDataRetrievedEvent`. Internally, `FapiCallbackService` uses `shouldCallUserInfo()` with the provider's `loginScopes` to determine the correct path: if the access token contains only login scopes, the ID token path is taken; otherwise the UserInfo endpoint is called.

Scope comparison reads the access token as an unverified JWT and expects a standard three-part compact JWT whose payload JSON includes a string `scope` claim (space-separated scope values, per OIDC). If the token is not a JWT, the payload cannot be decoded, or `scope` is missing or not a string, `UserInfoRequestException` is thrown instead of assuming `openid` only, so malformed tokens fail visibly during callback processing.

### Handling MyInfo Data

```php
use Accredifysg\SingPassLogin\Events\MyInfoDataRetrievedEvent;

class MyInfoDataRetrievedListener
{
    public function handle(MyInfoDataRetrievedEvent $event): void
    {
        $myInfoData = $event->getMyInfoData();
        $state = $event->getState();

        $user->update([
            'name' => $myInfoData['name']['value'] ?? null,
            'email' => $myInfoData['email']['value'] ?? null,
            'mobile' => $myInfoData['mobileno']['value'] ?? null,
            'nationality' => $myInfoData['nationality']['value'] ?? null,
            'date_of_birth' => $myInfoData['dob']['value'] ?? null,
        ]);
    }
}
```

### Available MyInfo Scopes

For the complete list, see the [MyInfo Data Catalog](https://docs.developer.singpass.gov.sg/docs/data-catalog-myinfo/catalog). The package validates requested scopes against the `available_scopes` in `config/myinfo.php`.

## CorpPass Integration

CorpPass uses the same FAPI 2.0 flow as SingPass, with a hierarchical entity + actor identity model. The entity represents the company/organisation (`sub`), and the actor represents the individual user (`act.sub`).

### Starting a CorpPass Login

```javascript
async function startCorpPassLogin(scopes) {
  const qs = new URLSearchParams({ scopes: scopes.join(',') });
  const res = await fetch(`/ndi/cp/login?${qs}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) throw new Error('CorpPass login bootstrap failed');
  const { redirect_url } = await res.json();
  window.location.assign(redirect_url);
}

// Login scopes only (entity + actor data in ID token)
await startCorpPassLogin(['openid', 'entity.identity', 'user.identity', 'user.name']);

// With UserInfo scopes (authorization data via UserInfo endpoint)
await startCorpPassLogin(['openid', 'entity.identity', 'user.identity', 'authinfo']);
```

### CorpPass Scopes

| Scope | Source | Description |
|---|---|---|
| `openid` | Required | Core OIDC scope |
| `entity.identity` | ID token | Entity type, registration number, COI |
| `entity.basic_profile.name` | ID token | Entity name |
| `entity.basic_profile.uen_status` | ID token | Entity UEN status |
| `user.identity` | ID token | Actor identity number (NRIC/FIN), COI |
| `user.name` | ID token | Actor name |
| `user.corppass.email` | ID token | Actor CorpPass email |
| `authinfo` | UserInfo | Authorization info for the entity |
| `tpauthinfo` | UserInfo | Third-party authorization info |

### Handling CorpPass Events

The CorpPass callback controller fires up to two events:

- **`CorpPassSuccessfulLoginEvent`** — Always fired when an ID token payload is present. Carries a `CorpPassUser` model.
- **`CorpPassDataRetrievedEvent`** — Fired when UserInfo scopes (`authinfo`, `tpauthinfo`) were requested. Carries the authorization data array.

```php
use Accredifysg\SingPassLogin\Events\CorpPassSuccessfulLoginEvent;

class CorpPassLoginListener
{
    public function handle(CorpPassSuccessfulLoginEvent $event): void
    {
        $corpPassUser = $event->getCorpPassUser();

        // Entity (company/organisation)
        $entityId = $corpPassUser->getEntityId();       // UEN
        $entityName = $corpPassUser->getEntityName();

        // Actor (individual user)
        $nric = $corpPassUser->getIdentityNumber();      // NRIC/FIN (requires user.identity scope)
        $name = $corpPassUser->getName();                // Requires user.name scope

        // Look up or create the user in your system
        $user = User::firstOrCreate(
            ['corppass_entity_id' => $entityId, 'nric' => $nric],
            ['name' => $name, 'entity_name' => $entityName],
        );

        Auth::login($user);
    }
}
```

```php
use Accredifysg\SingPassLogin\Events\CorpPassDataRetrievedEvent;

class CorpPassDataListener
{
    public function handle(CorpPassDataRetrievedEvent $event): void
    {
        $data = $event->getCorpPassData();

        // Authorization data from the UserInfo endpoint
        $authInfo = $data['auth_info'] ?? [];
        $tpAuthInfo = $data['tp_auth_info'] ?? [];
    }
}
```

### Registering CorpPass Listeners

Configure the built-in listener via `corppass-login.php`:

```php
'use_default_listener' => true,
'listener_class' => \App\Listeners\CorpPassLoginListener::class,
```

Or register manually in your `EventServiceProvider`:

```php
protected $listen = [
    CorpPassSuccessfulLoginEvent::class => [
        CorpPassLoginListener::class,
    ],
    CorpPassDataRetrievedEvent::class => [
        CorpPassDataListener::class,
    ],
];
```

## Event Flow Summary

| Flow | Initiation Route | Callback Route | Events |
|---|---|---|---|
| SingPass Login | `/ndi/sp/login` | `/ndi/sp/callback` | `SingPassSuccessfulLoginEvent` |
| MyInfo | `/ndi/mi/initiate` | `/ndi/mi/callback` | `MyInfoDataRetrievedEvent` |
| CorpPass | `/ndi/cp/login` | `/ndi/cp/callback` | `CorpPassSuccessfulLoginEvent`, `CorpPassDataRetrievedEvent` |

## Diagnostic Logging

Set `NDI_LOGS_ENABLED=true` in your `.env` to enable detailed logging of every step in the authentication flow. All log entries are prefixed with `[SingPass]` and sensitive values (`client_assertion`, `code_verifier`, `id_token`, `access_token`) are automatically redacted.

Logged steps include:

| Step | Info logged |
|---|---|
| OpenID Discovery | Endpoint, cached issuer, PAR endpoint |
| PAR Request | Endpoint, request params (redacted), `request_uri` on success |
| Auth Initiation | Client ID, redirect URI, scopes, state, session ID |
| Callback Validation | Session ID match, state lookup, missing session data details |
| Token Exchange | Endpoint, status, error details on failure |
| JWE / JWS | Decryption and signature verification steps |
| ID Token Claims | Expected vs actual `aud` and `iss` on verification failure |
| JWKS | Fetch endpoint, success/failure |
| UserInfo | Endpoint, JWE decryption, JWT verification |

This is particularly useful for diagnosing session issues (mismatched session IDs between login and callback), issuer/audience mismatches, and PAR rejections.

## Upgrading from pre–FAPI 2.0 versions

- The login route returns **JSON** with `redirect_url`; update clients to `fetch` (with credentials) then navigate.
- Ensure your app uses **session**-backed routes (default `web` middleware).
- Discovery metadata must include **`pushed_authorization_request_endpoint`**.
- Review `login_scopes` in `singpass-login.php` and `available_scopes` in `myinfo.php`.
- MyInfo now has **dedicated routes** (`/ndi/mi/initiate` and `/ndi/mi/callback`) and its own config file (`config/myinfo.php`) instead of sharing the login route and config.
- Configuration has been split into four files: `ndi.php` (shared), `singpass-login.php`, `myinfo.php`, and `corppass-login.php`. Several environment variables have been renamed (see Configuration section).
- The old `SingPassLoginFacade` and `SingPassLoginInterface` have been removed. If you were calling `SingPassLogin::handleCallback()` directly, the logic is now internal to the callback controllers.
- Exceptions have been renamed: `SingPassGetEndpointException` → `AuthFlowException`, `SingPassAuthenticationErrorException` → `AuthenticationErrorException`, `SingPassTokenException` → `TokenExchangeException`, `SingPassJwksException` → `JwksException`.
- Services have been renamed: `SingPassJwtService` → `JwtService`, `GetSingPassTokenService` → `TokenExchangeService`, `GetSingPassJwksService` → `JwksService`.
- **`SingPassUser::getNric()`** is deprecated; use the readonly **`nric`** property on `SingPassUser` instead.

## Exceptions

```php
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Exceptions\AuthenticationErrorException;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Exceptions\JweDecryptionFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Exceptions\JwksException;
use Accredifysg\SingPassLogin\Exceptions\JwtDecodeFailedException;
use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Exceptions\PushedAuthorizationRequestException;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Exceptions\TokenExchangeException;
use Accredifysg\SingPassLogin\Exceptions\MissingConfigException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;
```

### Configuration Exceptions

- **`MissingConfigException`**: A required config value (e.g. `singpass-login.client_id`) is `null`. Thrown by `ProviderConfig` factory methods with a message identifying the missing key.

### FAPI / PAR Exceptions

- **`PushedAuthorizationRequestException`**: The PAR endpoint returned an error or an invalid response (includes OAuth error codes when provided).
- **`AuthenticationErrorException`**: The provider returned an OAuth error to the callback (`error` / `error_description` query parameters).
- **`AuthFlowException`**: The callback request was missing required parameters, failed CSRF/session validation, or the ID token payload was invalid.

### Token / JWT Exceptions

- **`TokenExchangeException`**: The token endpoint returned an error, an unparseable response, or was missing `id_token`.
- **`JwtPayloadException`**: The decoded ID token payload failed validation (e.g. missing `sub` claim).
- **`JweDecryptionFailedException`** / **`JwtDecodeFailedException`**: JWE decryption or JWS verification of the ID token failed.
- **`JwksException`** / **`JwksInvalidException`**: JWKS retrieval or parsing failed.
- **`OpenIdDiscoveryException`**: OpenID discovery endpoint returned invalid or incomplete configuration.

### UserInfo Exceptions

- **`UserInfoRequestException`**: The UserInfo HTTP request failed, **or** the access token could not be inspected for scopes before choosing the ID token vs UserInfo path (invalid JWT shape, undecodable payload, missing or non-string `scope` claim). Callback handling treats these as hard failures rather than silently defaulting scopes.
- **`UserInfoDecryptionException`**: The UserInfo JWE token decryption failed.
- **`UserInfoVerificationException`**: The UserInfo JWS token verification failed.
