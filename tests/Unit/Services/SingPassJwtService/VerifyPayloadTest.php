<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services\SingPassJwtService;

use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;

class VerifyPayloadTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        // Set up default configuration values
        $app['config']->set('services.singpass-login.clientId', 'test-client-id');
        $app['config']->set('services.singpass-login.domain', 'test-domain');
    }

    public function test_verify_payload_success()
    {
        // Mock configuration values
        $clientId = 'test-client-id';
        $domain = 'test-domain';
        Config::set('services.singpass-login.clientId', $clientId);
        Config::set('services.singpass-login.domain', $domain);

        // Create a valid payload
        $now = Carbon::now()->timestamp;
        $payload = (object) [
            'iat' => $now - 60, // Issued 1 minute ago
            'exp' => $now + 60, // Expires in 1 minute
            'aud' => $clientId,
            'iss' => $domain,
        ];

        // Call the method
        SingPassJwtService::verifyPayload($payload);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_verify_payload_expired_token()
    {
        // Mock configuration values
        $clientId = 'test-client-id';
        $domain = 'test-domain';
        Config::set('services.singpass-login.clientId', $clientId);
        Config::set('services.singpass-login.domain', $domain);

        // Create an expired payload
        $now = Carbon::now()->timestamp;
        $payload = (object) [
            'iat' => $now - 120, // Issued 2 minutes ago
            'exp' => $now - 60,  // Expired 1 minute ago
            'aud' => $clientId,
            'iss' => $domain,
        ];

        // Expect the JwtPayloadException to be thrown
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Token times are invalid');

        // Call the method
        SingPassJwtService::verifyPayload($payload);
    }

    public function test_verify_payload_wrong_client_id()
    {
        // Mock configuration values
        $clientId = 'test-client-id';
        $domain = 'test-domain';
        Config::set('services.singpass-login.clientId', $clientId);
        Config::set('services.singpass-login.domain', $domain);

        // Create a payload with the wrong client ID
        $now = Carbon::now()->timestamp;
        $payload = (object) [
            'iat' => $now - 60, // Issued 1 minute ago
            'exp' => $now + 60, // Expires in 1 minute
            'aud' => 'wrong-client-id',
            'iss' => $domain,
        ];

        // Expect the JwtPayloadException to be thrown
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Wrong client ID');

        // Call the method
        SingPassJwtService::verifyPayload($payload);
    }

    public function test_verify_payload_wrong_principal()
    {
        // Mock configuration values
        $clientId = 'test-client-id';
        $domain = 'test-domain';
        Config::set('services.singpass-login.clientId', $clientId);
        Config::set('services.singpass-login.domain', $domain);

        // Create a payload with the wrong principal
        $now = Carbon::now()->timestamp;
        $payload = (object) [
            'iat' => $now - 60, // Issued 1 minute ago
            'exp' => $now + 60, // Expires in 1 minute
            'aud' => $clientId,
            'iss' => 'wrong-domain',
        ];

        // Expect the JwtPayloadException to be thrown
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Came from wrong principal');

        // Call the method
        SingPassJwtService::verifyPayload($payload);
    }
}
