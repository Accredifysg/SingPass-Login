<?php

namespace Accredifysg\SingPassLogin\Interfaces;

use Jose\Component\Core\JWK;

interface DPoPServiceInterface
{
    public function generateKeyPair(): JWK;

    public function generateProofJwt(JWK $privateKey, string $htm, string $htu, ?string $ath = null): string;

    public function computeAccessTokenHash(string $accessToken): string;

    public function storeKeyForState(string $state, JWK $key): void;

    public function retrieveKeyForState(string $state): ?JWK;

    public function clearKeyForState(string $state): void;
}
