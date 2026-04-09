<?php

namespace Accredifysg\SingPassLogin\Facades;

use Accredifysg\SingPassLogin\SingPassLogin;
use Illuminate\Support\Facades\Facade;
use Jose\Component\Core\JWK;

/**
 * @see SingPassLogin
 *
 * @method static void handleCallback(string $code, string $state, string $codeVerifier, JWK $dpopKey, string $clientId, string $redirectUri)
 */
class SingPassLoginFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SingPassLogin::class;
    }
}
