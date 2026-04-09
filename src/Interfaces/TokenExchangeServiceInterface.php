<?php

namespace Accredifysg\SingPassLogin\Interfaces;

use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Jose\Component\Core\JWK;

interface TokenExchangeServiceInterface
{
    public function getToken(string $code, string $codeVerifier, JWK $dpopKey, string $clientId, string $redirectUri, string $cacheKey): TokenResponseDto;
}
