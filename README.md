# SingPass-Login

[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=Accredifysg_SingPass-Login&metric=coverage&token=11b8dd252687c701584068be55e47e5e432056c8)](https://sonarcloud.io/summary/new_code?id=Accredifysg_SingPass-Login)

PHP Laravel Package for SingPass Login and MyInfo

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

Optionally, you can publish the listener that will listen to the SingPassLoginEvent and log the user in

```bash
php artisan vendor:publish --provider="Accredifysg\SingPassLogin\SingPassLoginServiceProvider" --tag="listener"
```

## Usage and Customisations

### Controllers and Routes
There are three default controllers that handle the login process

`GetJwksEndpointController` exposes your application's JWKS endpoint to be registered with SingPass. 
The default route for this controller is `/sp/jwks`

`GetAuthenticationEndpointController` provides the authentication endpoint to redirect the client's browser to.
The default route for this controller is `/sp/login`

`PostSingPassCallbackController` handles the callback from SingPass, and kick-starts the login process.
The default route for this controller is `/sp/callback`

If you prefer to set your own routes you can set `SINGPASS_USE_DEFAULT_ROUTES` to `false`, 
then edit `SINGPASS_JWKS_URL`, `SINGPASS_CALLBACK_URL`, and `SINGPASS_AUTHENTICATION_URL` in
your `.env` file and map your own routes. 

If you prefer to write your own controllers you can define them in the config file
`singpass-login.php` as `get_jwks_endpoint_controller`, `post_singpass_callback_controller` and `get_authentication_endpoint_controller`

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

This package supports retrieving user data from MyInfo through scope-based data retrieval. By default, the package performs authentication-only flow using the `openid` scope. To retrieve additional user data, you can request specific MyInfo scopes during the authentication process.

### How It Works

MyInfo functionality is scope-driven:
- **Authentication Only**: When only the `openid` scope is requested (default), the package performs standard authentication without calling the UserInfo endpoint
- **MyInfo Data Retrieval**: When additional MyInfo scopes are requested, the package calls the UserInfo endpoint after successful authentication to retrieve the consented user data

### Requesting MyInfo Scopes

Pass scopes as query parameters when redirecting users to the authentication endpoint:

**From JavaScript/Frontend:**
```javascript
// Basic authentication only (default behavior)
window.location.href = '/sp/login';

// Request basic profile information
const scopes = ['openid', 'name', 'email', 'mobileno'];
window.location.href = `/sp/login?scopes=${scopes.join(',')}`;

// Request extended user data
const extendedScopes = [
    'openid',
    'name',
    'email',
    'mobileno',
    'nationality',
    'dob'
];
window.location.href = `/sp/login?scopes=${extendedScopes.join(',')}`;
```

**From Laravel Controller:**
```php
public function redirectToSingPass()
{
    $scopes = ['openid', 'name', 'email', 'mobileno'];
    return redirect('/sp/login?' . http_build_query(['scopes' => implode(',', $scopes)]));
}
```

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
        // Authentication only (no MyInfo scopes)
        SingPassSuccessfulLoginEvent::class => [
            SingPassSuccessfulLoginListener::class,
        ],
        
        // MyInfo data retrieval (with additional scopes)
        MyInfoDataRetrievedEvent::class => [
            MyInfoDataRetrievedListener::class,
        ],
    ];
}
```

### Event Flow

- **Authentication Only**: When only `openid` scope is requested → `SingPassSuccessfulLoginEvent` is emitted
- **MyInfo Data Retrieval**: When additional MyInfo scopes are requested → `MyInfoDataRetrievedEvent` is emitted

This separation allows you to handle authentication-only flows differently from flows that include MyInfo data retrieval.

### Backward Compatibility

Existing implementations continue to work without any changes:
- Default behavior remains authentication-only with `openid` scope
- `SingPassSuccessfulLoginEvent` is still emitted for authentication-only flows
- No configuration changes required for existing applications

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

// MyInfo-specific exceptions
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;
```

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

