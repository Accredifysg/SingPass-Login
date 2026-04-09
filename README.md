# SingPass-Login

[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=Accredifysg_SingPass-Login&metric=coverage&token=11b8dd252687c701584068be55e47e5e432056c8)](https://sonarcloud.io/summary/new_code?id=Accredifysg_SingPass-Login)

PHP Laravel Package for SingPass Login and MyInfo. The authorization flow follows **FAPI 2.0–style** integration: **Pushed Authorization Requests (PAR)** with **DPoP** on the PAR, token, and UserInfo calls, **PKCE**, and private-key **JWT client assertions**. Your OpenID Provider metadata (discovery) must expose a `pushed_authorization_request_endpoint`; the package validates this when caching discovery.

<a href="https://api.singpass.gov.sg/library/login/developers/overview-at-a-glance" rel="noreferrer nofollow">Official SingPass Login Docs</a>

## Architecture

The package separates **shared FAPI 2.0 choreography** from **provider-specific logic**:

- **Shared layer** (`FapiAuthenticationService`, `FapiCallbackService`) handles discovery, PAR, DPoP, PKCE, token exchange, and JWE/JWS processing.
- **Thin controllers** for each flow (SingPass Login, MyInfo) delegate to the shared layer and fire provider-specific events.
- A `ProviderConfig` DTO encapsulates per-provider configuration (client ID, redirect URI, cache key, scopes).

This design makes it straightforward to add new FAPI 2.0 providers (e.g. CorpPass) without duplicating protocol logic.

## Installation

You can install the package via composer:

```bash
composer require accredifysg/singpass-login
```

Add the following variables to your `.env` file.

```.dotenv
# SingPass variables
SINGPASS_CLIENT_ID=
SINGPASS_REDIRECT_URI=
SINGPASS_DOMAIN=
SINGPASS_DISCOVERY_ENDPOINT=
SINGPASS_SIGNING_KID=
SINGPASS_JWKS=
SINGPASS_PRIVATE_JWKS=

# FAPI 2.0 / DPoP (optional; default algorithm is ES256)
SINGPASS_DPOP_SIGNING_ALGORITHM=ES256

# Login app authentication context (see SingPass integration guide)
SINGPASS_AUTH_CONTEXT_TYPE=APP_AUTHENTICATION_DEFAULT
# SINGPASS_AUTH_CONTEXT_MESSAGE=

# Default Routes
SINGPASS_USE_DEFAULT_ROUTES=true
SINGPASS_JWKS_URL=/ndi/jwks
SINGPASS_AUTHENTICATION_URL=/ndi/sp/login
SINGPASS_CALLBACK_URL=/ndi/sp/callback
SINGPASS_MYINFO_AUTHENTICATION_URL=/ndi/mi/initiate
SINGPASS_MYINFO_CALLBACK_URL=/ndi/mi/callback

# Default Listener
SINGPASS_USE_DEFAULT_LISTENER=true

# MyInfo credentials (required if you use MyInfo scopes)
SINGPASS_MYINFO_CLIENT_ID=
SINGPASS_MYINFO_REDIRECT_URI=
```

Publish the config file

```bash
php artisan vendor:publish --provider="Accredifysg\SingPassLogin\SingPassLoginServiceProvider" --tag="config"
```

Optionally, you can publish the listener that will listen to the `SingPassSuccessfulLoginEvent` and log the user in

```bash
php artisan vendor:publish --provider="Accredifysg\SingPassLogin\SingPassLoginServiceProvider" --tag="listener"
```

## Usage and Customisations

### Controllers and Routes

The package registers the following routes under the `web` middleware group:

| Route | Controller | Name | Purpose |
|---|---|---|---|
| `GET /ndi/sp/login` | `SingPass\LoginController` | `singpass.login` | Initiate SingPass Login (PAR + redirect URL) |
| `GET /ndi/sp/callback` | `SingPass\LoginCallbackController` | `singpass.callback` | Handle SingPass Login callback |
| `GET /ndi/mi/initiate` | `SingPass\MyInfoController` | `myinfo.login` | Initiate MyInfo flow (PAR + redirect URL) |
| `GET /ndi/mi/callback` | `SingPass\MyInfoCallbackController` | `myinfo.callback` | Handle MyInfo callback |
| `GET /ndi/jwks` | `GetJwksEndpointController` | `singpass.jwks` | Expose your application's JWKS |

Each auth controller returns **JSON** with a `redirect_url` the browser should navigate to. The callback controllers handle the OAuth redirect from SingPass, validate `state` (CSRF), exchange the code using DPoP, and fire the appropriate event.

If you prefer to set your own routes you can set `SINGPASS_USE_DEFAULT_ROUTES` to `false`, 
then configure the route URLs in your `.env` and map your own routes.

If you prefer to write your own controllers you can define them in the config file
`singpass-login.php` using the `*_controller` keys.

### Starting a Login

`GET /ndi/sp/login` returns `200` JSON: `{ "redirect_url": "..." }`. The browser (or SPA) should request that URL with **same-origin credentials** so the session cookie is sent, then navigate to `redirect_url`.

Optional query parameters for Login flows: `authentication_context_type` and `authentication_context_message` override `SINGPASS_AUTH_CONTEXT_TYPE` / `SINGPASS_AUTH_CONTEXT_MESSAGE` for that request. See the [SingPass authorization request documentation](https://docs.developer.singpass.gov.sg/docs/technical-specifications/integration-guide/1.-authorization-request#possible-authentication_context_type-values) for valid `authentication_context_type` values.

**From JavaScript (recommended):**

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

**From a Laravel Blade view or inline script**, use the same `fetch` pattern; a simple `redirect('/ndi/sp/login?...')` only sends the client to a JSON response, not to SingPass.

### Listener

If you published the default listener, you should edit it and map your user retrieval via NRIC accordingly.

```php
public function handle(SingPassSuccessfulLoginEvent $event): RedirectResponse
{
    $singPassUser = $event->getSingPassUser();
    $nric = $singPassUser->getNric();

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

If you prefer to write your own, you can set `SINGPASS_USE_DEFAULT_LISTENER` to `false` in
your `.env` and replace `listener_class` in the config file `singpass-login.php`.

## MyInfo Integration

MyInfo has its own dedicated routes (`/ndi/mi/initiate` and `/ndi/mi/callback`) and uses separate client credentials (`SINGPASS_MYINFO_CLIENT_ID` / `SINGPASS_MYINFO_REDIRECT_URI`).

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

// Request MyInfo scopes
await startMyInfo(['openid', 'name', 'email', 'mobileno', 'nationality', 'dob']);
```

### How It Works

The MyInfo callback controller calls the UserInfo endpoint (with DPoP) to retrieve the requested data and emits `MyInfoDataRetrievedEvent`. The SingPass Login callback uses the ID token path and emits `SingPassSuccessfulLoginEvent`.

Internally, `FapiCallbackService` uses `shouldCallUserInfo()` with the provider's `loginScopes` configuration to determine the correct path: if the access token contains only login scopes, the ID token path is taken; otherwise the UserInfo endpoint is called.

### Available MyInfo Scopes

For the complete and up-to-date list of available MyInfo data items and their descriptions, refer to the official MyInfo Data Catalog:

**[MyInfo Data Catalog Documentation](https://docs.developer.singpass.gov.sg/docs/data-catalog-myinfo/catalog)**

The package validates requested scopes against the `available_scopes` configuration. Invalid scopes throw an `InvalidArgumentException`.

### Handling MyInfo Data

When MyInfo data is successfully retrieved, the package emits a `MyInfoDataRetrievedEvent`:

```php
<?php

namespace App\Listeners;

use Accredifysg\SingPassLogin\Events\MyInfoDataRetrievedEvent;

class MyInfoDataRetrievedListener
{
    public function handle(MyInfoDataRetrievedEvent $event): void
    {
        $myInfoData = $event->getMyInfoData();
        $state = $event->getState();
        
        // Update user profile with MyInfo data
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

#### MyInfo Data Structure

MyInfo data is returned as an associative array nested under `person_info`. Each field typically contains:
- `value`: The actual data value
- `source`: Data source identifier (e.g., '1' for government-verified)
- `classification`: Data classification level
- `lastupdated`: Timestamp of last update

Example structure:
```php
[
    'name' => [
        'value' => 'John Doe',
        'source' => '1',
        'classification' => 'C',
        'lastupdated' => '2023-01-15'
    ],
    'email' => [
        'value' => 'john@example.com',
        'source' => '2',
        'classification' => 'C',
        'lastupdated' => '2023-01-15'
    ],
    // Additional fields based on requested scopes
]
```

#### Registering the Listener

Register the listener in your `EventServiceProvider`:

```php
<?php

namespace App\Providers;

use Accredifysg\SingPassLogin\Events\MyInfoDataRetrievedEvent;
use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use App\Listeners\MyInfoDataRetrievedListener;
use App\Listeners\SingPassSuccessfulLoginListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SingPassSuccessfulLoginEvent::class => [
            SingPassSuccessfulLoginListener::class,
        ],
        
        MyInfoDataRetrievedEvent::class => [
            MyInfoDataRetrievedListener::class,
        ],
    ];
}
```

### Event Flow

- **SingPass Login** (`/ndi/sp/login` → `/ndi/sp/callback`): `SingPassSuccessfulLoginEvent` with a `SingPassUser` model
- **MyInfo** (`/ndi/mi/initiate` → `/ndi/mi/callback`): `MyInfoDataRetrievedEvent` with the UserInfo data array

### Upgrading from pre–FAPI 2.0 versions

- The login route returns **JSON** with `redirect_url`; update clients to `fetch` (with credentials) then navigate.
- Ensure your app uses **session**-backed routes (default `web` middleware).
- Discovery metadata must include **`pushed_authorization_request_endpoint`**.
- Review `login_scopes` and `available_scopes` in `singpass-login.php`.
- MyInfo now has **dedicated routes** (`/ndi/mi/initiate` and `/ndi/mi/callback`) instead of sharing the login route.
- The old `SingPassLoginFacade` and `SingPassLoginInterface` have been removed. If you were calling `SingPassLogin::handleCallback()` directly, the logic is now internal to the callback controllers.
- Exceptions have been renamed: `SingPassGetEndpointException` → `AuthFlowException`, `SingPassAuthenticationErrorException` → `AuthenticationErrorException`, `SingPassTokenException` → `TokenExchangeException`, `SingPassJwksException` → `JwksException`.
- Services have been renamed: `SingPassJwtService` → `JwtService`, `GetSingPassTokenService` → `TokenExchangeService`, `GetSingPassJwksService` → `JwksService`.

## Exceptions

```php
<?php
use Accredifysg\SingPassLogin\Exceptions\JweDecryptionFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Exceptions\JwtDecodeFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Exceptions\JwksException;
use Accredifysg\SingPassLogin\Exceptions\TokenExchangeException;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Exceptions\AuthenticationErrorException;
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Exceptions\PushedAuthorizationRequestException;

// MyInfo-specific exceptions
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;
```

### FAPI / PAR exception handling

- **`PushedAuthorizationRequestException`**: The PAR endpoint returned an error or an invalid response (includes OAuth error codes when provided).
- **`AuthenticationErrorException`**: SingPass returned an OAuth error to the callback (`error` / `error_description` query parameters).
- **`AuthFlowException`**: The callback request was missing required parameters or failed CSRF/session validation.

### MyInfo Exception Handling

When retrieving MyInfo data, the following exceptions may be thrown:

- **`UserInfoRequestException`**: Thrown when the UserInfo endpoint HTTP request fails. Includes HTTP status code and endpoint URL.
- **`UserInfoDecryptionException`**: Thrown when the UserInfo JWE token decryption fails. Includes decryption failure details.
- **`UserInfoVerificationException`**: Thrown when the UserInfo JWS token verification fails. Includes verification failure reason.

```php
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;

try {
    // MyInfo data retrieval happens automatically during callback
} catch (UserInfoRequestException $e) {
    Log::error('MyInfo request failed: ' . $e->getMessage());
} catch (UserInfoDecryptionException $e) {
    Log::error('MyInfo decryption failed: ' . $e->getMessage());
} catch (UserInfoVerificationException $e) {
    Log::error('MyInfo verification failed: ' . $e->getMessage());
}
```
