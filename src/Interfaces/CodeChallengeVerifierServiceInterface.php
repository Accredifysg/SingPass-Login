<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Interfaces;

interface CodeChallengeVerifierServiceInterface
{
    public function generateCodeVerifier(): string;

    public function generateCodeChallenge(string $codeVerifier): string;
}
