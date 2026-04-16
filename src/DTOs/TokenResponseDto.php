<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\DTOs;

readonly class TokenResponseDto
{
    public function __construct(
        public string $idToken,
        public ?string $accessToken = null
    ) {}

    /** @phpstan-assert-if-true string $this->accessToken */
    public function hasAccessToken(): bool
    {
        return $this->accessToken !== null;
    }
}
