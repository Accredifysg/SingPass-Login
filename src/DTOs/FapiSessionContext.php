<?php

namespace Accredifysg\SingPassLogin\DTOs;

use Jose\Component\Core\JWK;

readonly class FapiSessionContext
{
    public function __construct(
        public string $code,
        public string $state,
        public string $codeVerifier,
        public JWK $dpopKey,
        public string $clientId,
        public string $redirectUri,
    ) {}
}
