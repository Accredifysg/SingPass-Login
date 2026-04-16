<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\Services\CodeChallengeVerifierService;
use Accredifysg\SingPassLogin\Tests\TestCase;

class CodeChallengeVerifierServiceTest extends TestCase
{
    private CodeChallengeVerifierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CodeChallengeVerifierService;
    }

    public function test_generate_code_verifier_returns_valid_string(): void
    {
        $codeVerifier = $this->service->generateCodeVerifier();

        // Assert that the code verifier is a non-empty string
        $this->assertNotEmpty($codeVerifier);

        // Assert that the code verifier only contains valid characters
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $codeVerifier);

        // Assert that the code verifier has a reasonable length
        $this->assertGreaterThanOrEqual(43, strlen($codeVerifier));
        $this->assertLessThanOrEqual(128, strlen($codeVerifier));
    }

    public function test_generate_code_challenge_returns_valid_string(): void
    {
        $codeVerifier = $this->service->generateCodeVerifier();
        $codeChallenge = $this->service->generateCodeChallenge($codeVerifier);

        // Assert that the code challenge is a non-empty string
        $this->assertNotEmpty($codeChallenge);

        // Assert that the code challenge only contains valid characters
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $codeChallenge);

        // Assert that the code challenge has a reasonable length
        $this->assertGreaterThanOrEqual(43, strlen($codeChallenge));
        $this->assertLessThanOrEqual(128, strlen($codeChallenge));
    }

    public function test_generate_code_challenge_is_deterministic(): void
    {
        $codeVerifier = $this->service->generateCodeVerifier();

        // Generate code challenge twice with the same verifier
        $codeChallenge1 = $this->service->generateCodeChallenge($codeVerifier);
        $codeChallenge2 = $this->service->generateCodeChallenge($codeVerifier);

        // Assert that both challenges are identical
        $this->assertEquals($codeChallenge1, $codeChallenge2);
    }
}
