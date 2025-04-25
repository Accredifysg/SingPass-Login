<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Interfaces\CodeChallengeVerifierServiceInterface;
use Random\RandomException;

final class CodeChallengeVerifierService implements CodeChallengeVerifierServiceInterface
{
    /**
     * @throws RandomException
     */
    public function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    public function generateCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
