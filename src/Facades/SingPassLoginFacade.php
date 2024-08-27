<?php

namespace Accredifysg\SingPassLogin\Facades;

use Accredifysg\SingPassLogin\SingPassLogin;
use Illuminate\Support\Facades\Facade;

/**
 * @see SingPassLogin
 */
class SingPassLoginFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SingPassLogin::class;
    }
}
