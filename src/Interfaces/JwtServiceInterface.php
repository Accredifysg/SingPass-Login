<?php

namespace Accredifysg\SingPassLogin\Interfaces;

use Jose\Component\Core\JWKSet;

interface JwtServiceInterface
{
    public function jweDecrypt(string $jweToken): string;

    /**
     * @return array<string, mixed>
     */
    public function jwtDecode(string $jwtToken, JWKSet $jwksKeyset): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyPayload(array $payload, string $clientId, string $issuerDomain): void;
}
