<?php

namespace Accredifysg\SingPassLogin\Interfaces;

use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;

interface GetSingPassTokenServiceInterface
{
    public function getToken(string $code, string $codeVerifier, string $state): TokenResponseDto;
}
