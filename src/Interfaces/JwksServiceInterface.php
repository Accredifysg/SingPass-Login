<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Interfaces;

use Jose\Component\Core\JWKSet;

interface JwksServiceInterface
{
    public function getJwks(string $cacheKey): JWKSet;
}
