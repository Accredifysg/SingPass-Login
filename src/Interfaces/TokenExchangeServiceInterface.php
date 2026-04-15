<?php

namespace Accredifysg\SingPassLogin\Interfaces;

use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Accredifysg\SingPassLogin\Exceptions\TokenExchangeException;
use Illuminate\Http\Client\ConnectionException;
use Jose\Component\Core\JWK;

interface TokenExchangeServiceInterface
{
    /**
     * @throws ConnectionException
     * @throws TokenExchangeException
     */
    public function getToken(string $code, string $codeVerifier, JWK $dpopKey, string $clientId, string $redirectUri, string $cacheKey): TokenResponseDto;
}
