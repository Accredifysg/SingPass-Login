<?php

namespace Accredifysg\SingPassLogin\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Accredifysg\SingPassLogin\SingPassLogin
 */
class SingPassLoginFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Accredifysg\SingPassLogin\SingPassLogin::class;
    }
}
