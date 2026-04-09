# SingPass-Login

[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=Accredifysg_SingPass-Login&metric=coverage&token=11b8dd252687c701584068be55e47e5e432056c8)](https://sonarcloud.io/summary/new_code?id=Accredifysg_SingPass-Login)

PHP Laravel Package for SingPass Login and MyInfo. The authorization flow follows **FAPI 2.0–style** integration: **Pushed Authorization Requests (PAR)** with **DPoP** on the PAR, token, and UserInfo calls, **PKCE**, and private-key **JWT client assertions**. Your OpenID Provider metadata (discovery) must expose a `pushed_authorization_request_endpoint`; the package validates this when caching discovery.

<a href="https://api.singpass.gov.sg/library/login/developers/overview-at-a-glance" rel="noreferrer nofollow">Official SingPass Login Docs</a>

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
SINGPASS_JWKS_URL=/sp/jwks
SINGPASS_AUTHENTICATION_URL=/sp/login
SINGPASS_CALLBACK_URL=/sp/callback

# Default Listener
SINGPASS_USE_DEFAULT_LISTENER=true

# Optional MyInfo envs if you want to use MyInfo integration
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
There are three default controllers that handle the login process.

`GetJwksEndpointController` exposes your application's JWKS endpoint to be registered with SingPass.
The default route for this controller is `/sp/jwks`.

`GetAuthenticationEndpointController` runs the PAR step server-side and returns **JSON** with a `redirect_url` the browser should open (SingPass’s authorization endpoint with `client_id` and `request_uri` only). The default route is `/sp/login`. You must call it from a context where the **`web` middleware group runs and sessions work** (the package registers these routes with `web` middleware). The controller stores PKCE verifier, ephemeral DPoP keys, `client_id`, and `redirect_uri` in the session keyed by `state` for CSRF protection and token exchange.

`GetSingPassCallbackController` handles the OAuth callback from SingPass, validates `state`, exchanges the code using DPoP, and runs the rest of the login flow.
The default route for this controller is `/sp/callback`.

If you prefer to set your own routes you can set `SINGPASS_USE_DEFAULT_ROUTES` to `false`, 
then edit `SINGPASS_JWKS_URL`, `SINGPASS_CALLBACK_URL`, and `SINGPASS_AUTHENTICATION_URL` in
your `.env` file and map your own routes. 

If you prefer to write your own controllers you can define them in the config file
`singpass-login.php` as `get_jwks_endpoint_controller`, `post_singpass_callback_controller`, and `get_authentication_endpoint_controller`.

### Starting a login (JSON redirect_url)

`GET /sp/login` returns `200` JSON: `{ "redirect_url": "..." }`. The browser (or SPA) should request that URL with **same-origin credentials** so the session cookie is sent, then navigate to `redirect_url`.

Optional query parameters for **Login** flows (no MyInfo scopes): `authentication_context_type` and `authentication_context_message` override `SINGPASS_AUTH_CONTEXT_TYPE` / `SINGPASS_AUTH_CONTEXT_MESSAGE` for that request. See the [SingPass authorization request documentation](https://docs.developer.singpass.gov.sg/docs/technical-specifications/integration-guide/1.-authorization-request#possible-authentication_context_type-values) for valid `authentication_context_type` values.

**From JavaScript (recommended):**

```javascript
async function startSingPassLogin(scopes) {
  const qs = new URLSearchParams({ scopes: scopes.join(',') });
  const res = await fetch(`/sp/login?${qs}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) throw new Error('Login bootstrap failed');
  const { redirect_url } = await res.json();
  window.location.assign(redirect_url);
}

// Example: login scopes only (see config `login_scopes`)
await startSingPassLogin(['openid', 'name', 'email', 'mobileno']);
```

**From a Laravel Blade view or inline script**, use the same `fetch` pattern; a simple `redirect('/sp/login?...')` only sends the client to a JSON response, not to SingPass.

### Listener
If you published the default listener, you should edit it and map your user retrieval via NRIC accordingly.
```php
public function handle(SingPassSuccessfulLoginEvent $event): RedirectResponse
    {
        $singPassUser = $event->getSingPassUser();
        $nric = $singPassUser->getNric();

        $user = User::where('nric', '=', $nric)->first(); // Map to your own model that stores the users' NRIC or UUID

        if (! $user) {
            throw new SingPassLoginException;
        }

        Auth::login($user);
    }
```

If you prefer to write your own, you can set `SINGPASS_USE_DEFAULT_LISTENER` to `false` in
your `.env` and replace `listener_class` in the config file `singpass-login.php`

## MyInfo Integration

This package supports retrieving user data from MyInfo through scope-based data retrieval. **Login scopes** (config key `login_scopes`, including `openid`, `user.identity`, `name`, `email`, `mobileno`, etc.) are satisfied from the ID token where applicable; **any other requested scope** that is not a login scope is treated as a MyInfo scope and triggers the UserInfo endpoint (with DPoP), using `SINGPASS_MYINFO_CLIENT_ID` and `SINGPASS_MYINFO_REDIRECT_URI`.

### How It Works

MyInfo behaviour is scope-driven:

- **Login-only data**: When every requested scope is in `login_scopes`, the package does **not** call the UserInfo endpoint; profile fields available from the ID token are mapped on `SingPassUser` where supported.
- **MyInfo data retrieval**: When at least one scope is **not** in `login_scopes`, the package uses the MyInfo client credentials, calls UserInfo after authentication, and emits `MyInfoDataRetrievedEvent`.

### Requesting MyInfo Scopes

Pass scopes as the `scopes` query parameter on `GET /sp/login`, then follow the JSON `redirect_url` using the same `fetch` pattern as in **Starting a login**.

**From JavaScript/Frontend:**

```javascript
async function startSingPassLogin(scopes) {
  const qs = new URLSearchParams({ scopes: scopes.join(',') });
  const res = await fetch(`/sp/login?${qs}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  const { redirect_url } = await res.json();
  window.location.assign(redirect_url);
}

// Login scopes only (no UserInfo call)
await startSingPassLogin(['openid', 'name', 'email', 'mobileno']);

// Includes MyInfo-only scopes → UserInfo + MyInfoDataRetrievedEvent
await startSingPassLogin(['openid', 'name', 'email', 'mobileno', 'nationality', 'dob']);
```

Render a page or button that runs the above; avoid `window.location.href = '/sp/login'` alone because the route returns JSON, not an HTTP redirect.

### Available MyInfo Scopes

For the complete and up-to-date list of available MyInfo data items and their descriptions, refer to the official MyInfo Data Catalog:

**[MyInfo Data Catalog Documentation](https://docs.developer.singpass.gov.sg/docs/data-catalog-myinfo/catalog)**

The package validates requested scopes against the `available_scopes` configuration. Invalid scopes are filtered out and logged as warnings.

### Handling MyInfo Data

When MyInfo scopes are requested and data is successfully retrieved, the package emits a `MyInfoDataRetrievedEvent` instead of the standard `SingPassSuccessfulLoginEvent`.

#### MyInfoDataRetrievedEvent

Create a listener to handle the MyInfo data:

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
        if ($myInfoData) {
            $user->update([
                'name' => $myInfoData['name']['value'] ?? null,
                'email' => $myInfoData['email']['value'] ?? null,
                'mobile' => $myInfoData['mobileno']['value'] ?? null,
                'nationality' => $myInfoData['nationality']['value'] ?? null,
                'date_of_birth' => $myInfoData['dob']['value'] ?? null,
            ]);
        }
    }
}
```

#### MyInfo Data Structure

MyInfo data is returned as an associative array. Each field typically contains:
- `value`: The actual data value
- `source`: Data source identifier (e.g., '1' for government-verified)
- `classification`: Data classification level
- `lastupdated`: Timestamp of last update

Example structure:
```php
[
    'sub' => 's=S1234567A,u=UUID',
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
    'mobileno' => [
        'value' => '+6591234567',
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
        // Login scopes only (no UserInfo / MyInfoDataRetrievedEvent)
        SingPassSuccessfulLoginEvent::class => [
            SingPassSuccessfulLoginListener::class,
        ],
        
        // MyInfo scopes (UserInfo retrieval)
        MyInfoDataRetrievedEvent::class => [
            MyInfoDataRetrievedListener::class,
        ],
    ];
}
```

### Event Flow

- **Login scopes only**: All requested scopes are in `login_scopes` → `SingPassSuccessfulLoginEvent` is emitted
- **MyInfo scopes present**: At least one scope is outside `login_scopes` → `MyInfoDataRetrievedEvent` is emitted

This separation allows you to handle login-only flows differently from flows that include MyInfo UserInfo retrieval.

### Upgrading from pre–FAPI 2.0 versions

- The login route returns **JSON** with `redirect_url`; update clients to `fetch` (with credentials) then navigate.
- Ensure your app uses **session**-backed routes for `/sp/login` and `/sp/callback` (default `web` middleware).
- Discovery metadata must include **`pushed_authorization_request_endpoint`**.
- Review `login_scopes` and `available_scopes` in `singpass-login.php` (including FAPI login scopes such as `user.identity`).

## Exceptions
```php
<?php
use Accredifysg\SingPassLogin\Exceptions\JweDecryptionFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Exceptions\JwtDecodeFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Exceptions\SingPassJwksException;
use Accredifysg\SingPassLogin\Exceptions\SingPassTokenException;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Exceptions\SingPassAuthenticationErrorException;
use Accredifysg\SingPassLogin\Exceptions\PushedAuthorizationRequestException;

// MyInfo-specific exceptions
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;
```

### FAPI / PAR exception handling

- **`PushedAuthorizationRequestException`**: The PAR endpoint returned an error or an invalid response (includes OAuth error codes when provided).
- **`SingPassAuthenticationErrorException`**: SingPass returned an OAuth error to the callback (`error` / `error_description` query parameters).

### MyInfo Exception Handling

When retrieving MyInfo data, the following exceptions may be thrown:

- **`UserInfoRequestException`**: Thrown when the UserInfo endpoint HTTP request fails. Includes HTTP status code and endpoint URL.
- **`UserInfoDecryptionException`**: Thrown when the UserInfo JWE token decryption fails. Includes decryption failure details.
- **`UserInfoVerificationException`**: Thrown when the UserInfo JWS token verification fails. Includes verification failure reason.

All MyInfo exceptions extend `SingPassLoginException` and can be caught and handled in your application:

```php
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;

try {
    // MyInfo data retrieval happens automatically during callback
} catch (UserInfoRequestException $e) {
    // Handle UserInfo endpoint failure
    Log::error('MyInfo request failed: ' . $e->getMessage());
} catch (UserInfoDecryptionException $e) {
    // Handle decryption failure
    Log::error('MyInfo decryption failed: ' . $e->getMessage());
} catch (UserInfoVerificationException $e) {
    // Handle verification failure
    Log::error('MyInfo verification failed: ' . $e->getMessage());
}
```

