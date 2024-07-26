<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services\SingPassJwtService;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;

class GenerateClientAssertionTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        // Set up default configuration values
        $app['config']->set('services.singpass-login.clientId', 'test-client-id');
        $app['config']->set('services.singpass-login.signingKid', 'test-signing-kid');
    }

    public function test_generate_client_assertion_success()
    {
        // Mock Cache to return expected 'openId' values
        Cache::shouldReceive('get')
            ->with('openId')
            ->andReturn((object) [
                'issuer' => 'https://example.com',
            ]);

        // Create a mock JWK object
        $jwk = new JWK([
            'kty' => 'EC',
            'd' => 'AMLSmZWRqxafLBkg88gNp-jf3KD9WqYo66RsBIjUBM76OwVOqgHmUR5LhtReXBTiziXaVrWo1bPAZgfn7u_vpK11',
            'use' => 'sig',
            'crv' => 'P-521',
            'kid' => 'test-signing-kid',
            'x' => 'Abyt-Y7n4eBXxDaV3TdUjcyHstOxdaG427PDy77uDlGHg4KgwLh512UsTlaKpdF-E4gQjykbCNulwZHdGZHb3Qxe',
            'y' => 'AMTLon1XR5Ve71-t5AXPFPB3O42Ac96wlaHh6wnOkpJYO92_lzL3JEDu32i7alkckl8CrW6SlQCHJ6CFBBL4g2dk',
            'alg' => 'ES512',
        ]);

        // Call the method
        $clientAssertion = SingPassJwtService::generateClientAssertion($jwk);

        // Assert the client assertion is a non-empty string
        $this->assertIsString($clientAssertion);
        $this->assertNotEmpty($clientAssertion);

        // Further validate the JWS structure
        $serializer = new JwsCompactSerializer();
        $jws = $serializer->unserialize($clientAssertion);

        $this->assertEquals(1, $jws->countSignatures());

        $payload = json_decode($jws->getPayload(), true);

        $this->assertEquals('test-client-id', $payload['sub']);
        $this->assertEquals('https://example.com', $payload['aud']);
        $this->assertEquals('test-client-id', $payload['iss']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function test_generate_client_assertion_jwk_failure()
    {
        // Mock Cache to return expected 'openId' values
        Cache::shouldReceive('get')
            ->with('openId')
            ->andReturn((object) [
                'issuer' => 'https://example.com',
            ]);

        // Create a mock JWK object
        $jwk = new JWK([
            'kty' => '',
            'd' => '',
            'use' => '',
            'crv' => '',
            'kid' => '',
            'x' => '',
            'y' => '',
            'alg' => '',
        ]);

        // Expect the SingPassTokenException to be thrown
        $this->expectException(JwksInvalidException::class);

        // Call the method
        SingPassJwtService::generateClientAssertion($jwk);
    }
}
