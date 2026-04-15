<?php

namespace Accredifysg\SingPassLogin\DTOs;

readonly class FapiCallbackResult
{
    /**
     * @param  array<string, mixed>|null  $idTokenPayload
     * @param  array<string, mixed>|null  $userInfoData
     */
    public function __construct(
        public ?array $idTokenPayload,
        public ?array $userInfoData,
    ) {}
}
