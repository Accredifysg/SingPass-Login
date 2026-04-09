<?php

namespace Accredifysg\SingPassLogin\Facades;

use Accredifysg\SingPassLogin\SingPassLogin;
use Illuminate\Support\Facades\Facade;

/**
 * @see SingPassLogin
 *
 * @method static void handleCallback(string $code, string $state, string $codeVerifier, \Jose\Component\Core\JWK $dpopKey)
 */
class SingPassLoginFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SingPassLogin::class;
    }
}
