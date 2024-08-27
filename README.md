# SingPass-Login

[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=Accredifysg_SingPass-Login&metric=coverage&token=11b8dd252687c701584068be55e47e5e432056c8)](https://sonarcloud.io/summary/new_code?id=Accredifysg_SingPass-Login)

PHP Laravel Package for SingPass Login

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
SINGPASS_PRIVATE_EXPONENT=
SINGPASS_ENCRYPTION_KEY=
SINGPASS_JWKS=

# Default Routes
SINGPASS_USE_DEFAULT_ROUTES=true
SINGPASS_JWKS_URL=/sp/jwks
SINGPASS_CALLBACK_URL=/sp/callback

# Default Listener
SINGPASS_USE_DEFAULT_LISTENER=true
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
There are two default controllers that handle the login process

`GetJwksEndpointController` exposes your application's JWKS endpoint to be registered with SingPass. 
The default route for this controller is `/sp/jwks`

`PostSingPassCallbackController` handles the callback from SingPass, and kick-starts the login process.
The default route for this controller is `/sp/callback`

If you prefer to set your own routes you can set `SINGPASS_USE_DEFAULT_ROUTES` to `false`, 
then edit `SINGPASS_JWKS_URL` and `SINGPASS_CALLBACK_URL` in
your `.env` file and map your own routes. 

If you prefer to write your own controllers you can define them in the config file
`SingPass-Login.php` as `get_jwks_endpoint_controller` and `post_singpass_callback_controller`

### Listener
If you published the default listener, you should edit it and map your user retrieval via NRIC accordingly.
```php
public function handle(SingPassSuccessfulLoginEvent $event): RedirectResponse
    {
        $singPassUser = $event->getSingPassUser();
        $nric = $singPassUser->getNric();

        $user = User::where('nric', '=', $nric)->first(); // Map to your own model that stores the users' NRIC or UUID

        if (! $user) {
            throw new ModelNotFoundException;
        }

        Auth::login($user);

        return redirect()->intended();
    }
```

If you prefer to write your own, you can set `SINGPASS_USE_DEFAULT_LISTENER` to `false` in
your `.env` and replace `listener_class` in the config file `SingPass-Login.php`

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
```

