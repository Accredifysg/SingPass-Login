<?php

namespace Accredifysg\SingPassLogin\DTOs;

readonly class TokenResponseDto
{
    public function __construct(
        public string $idToken,
        public ?string $accessToken = null
    ) {}

    public function hasAccessToken(): bool
    {
        return $this->accessToken !== null;
    }
}
