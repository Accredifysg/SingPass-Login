<?php

namespace Accredifysg\SingPassLogin\Interfaces;

use Jose\Component\Core\JWKSet;

interface SingPassJwtServiceInterface
{
    public function jweDecrypt(string $jweToken): string;
    public function jwtDecode(string $jwtToken, JWKSet $jwksKeyset): array;
    public function verifyPayload(array $payload): void;
}
