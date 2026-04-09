<?php

namespace Accredifysg\SingPassLogin\Interfaces;

use Jose\Component\Core\JWK;

interface SingPassLoginInterface
{
    public function handleCallback(string $code, string $state, string $codeVerifier, JWK $dpopKey): void;
}
