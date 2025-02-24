<?php

namespace Accredifysg\SingPassLogin\Interfaces;

use Jose\Component\Core\JWKSet;

interface GetSingPassJwksServiceInterface
{
    public function getSingPassJwks(): JWKSet;
}
