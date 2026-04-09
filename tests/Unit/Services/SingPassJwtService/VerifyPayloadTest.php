<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services\SingPassJwtService;

use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Services\JwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class VerifyPayloadTest extends TestCase
{
    public function test_verify_payload_success(): void
    {
        $clientId = 'test-client-id';
        $domain = 'test-domain';

        // Create a valid payload
        $now = (int) Carbon::now()->timestamp;
        $payload = [
            'iat' => $now - 60,
            'exp' => $now + 60,
            'aud' => $clientId,
            'iss' => $domain,
        ];

        (new JwtService)->verifyPayload($payload, $clientId, $domain);

        $this->expectNotToPerformAssertions();
    }

    public function test_verify_payload_expired_token(): void
    {
        $clientId = 'test-client-id';
        $domain = 'test-domain';

        // Create an expired payload
        $now = (int) Carbon::now()->timestamp;
        $payload = [
            'iat' => $now - 120,
            'exp' => $now - 60,
            'aud' => $clientId,
            'iss' => $domain,
        ];

        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('The token expired.');

        (new JwtService)->verifyPayload($payload, $clientId, $domain);
    }

    public function test_verify_payload_wrong_client_id(): void
    {
        $clientId = 'test-client-id';
        $domain = 'test-domain';

        // Create a payload with the wrong client ID
        $now = (int) Carbon::now()->timestamp;
        $payload = [
            'iat' => $now - 60,
            'exp' => $now + 60,
            'aud' => 'wrong-client-id',
            'iss' => $domain,
        ];

        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Bad audience.');

        (new JwtService)->verifyPayload($payload, $clientId, $domain);
    }

    public function test_verify_payload_wrong_principal(): void
    {
        $clientId = 'test-client-id';
        $domain = 'test-domain';
        Config::set('singpass-login.client_id', $clientId);
        Config::set('singpass-login.domain', $domain);

        $now = (int) Carbon::now()->timestamp;
        $payload = [
            'iat' => $now - 60,
            'exp' => $now + 60,
            'aud' => $clientId,
            'iss' => 'wrong-domain',
        ];

        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Unknown issuer.');

        (new JwtService)->verifyPayload($payload, $clientId, $domain);
    }
}
