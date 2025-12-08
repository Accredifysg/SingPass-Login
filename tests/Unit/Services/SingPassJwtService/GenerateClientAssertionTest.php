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
    protected function defineEnvironment($app): void
    {
        // Set up default configuration values
        $app['config']->set('singpass-login.client_id', 'test-client-id');
        $app['config']->set('singpass-login.signing_kid', 'test-signing-kid');
    }

    public function test_generate_client_assertion_success(): void
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

        // Call the method with explicit client ID
        $clientAssertion = SingPassJwtService::generateClientAssertion($jwk, 'mock-code', 'test-client-id');

        // Assert the client assertion is a non-empty string
        $this->assertNotEmpty($clientAssertion);

        // Further validate the JWS structure
        $serializer = new JwsCompactSerializer;
        $jws = $serializer->unserialize($clientAssertion);

        $this->assertEquals(1, $jws->countSignatures());

        $payload = json_decode($jws->getPayload() ?: '{}', true);

        $this->assertEquals('test-client-id', $payload['sub']);
        $this->assertEquals('https://example.com', $payload['aud']);
        $this->assertEquals('test-client-id', $payload['iss']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertEquals('mock-code', $payload['code']);
    }

    public function test_generate_client_assertion_with_myinfo_client_id(): void
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

        // Call the method with MyInfo client ID
        $clientAssertion = SingPassJwtService::generateClientAssertion($jwk, 'mock-code', 'myinfo-client-id');

        // Assert the client assertion is a non-empty string
        $this->assertNotEmpty($clientAssertion);

        // Further validate the JWS structure
        $serializer = new JwsCompactSerializer;
        $jws = $serializer->unserialize($clientAssertion);

        $this->assertEquals(1, $jws->countSignatures());

        $payload = json_decode($jws->getPayload() ?: '{}', true);

        // Verify MyInfo client ID is used in sub and iss claims
        $this->assertEquals('myinfo-client-id', $payload['sub']);
        $this->assertEquals('https://example.com', $payload['aud']);
        $this->assertEquals('myinfo-client-id', $payload['iss']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertEquals('mock-code', $payload['code']);
    }

    public function test_generate_client_assertion_jwk_failure(): void
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
        SingPassJwtService::generateClientAssertion($jwk, 'mock-code', 'test-client-id');
    }

    public function test_generate_client_assertion_json_encode_failure(): void
    {
        // This test covers line 93 by attempting to trigger json_encode failure
        // While it's difficult to make json_encode return false in normal circumstances,
        // we can test with malformed UTF-8 or set json_encode to encounter recursion depth

        // Mock Cache to return a value that will cause json_encode issues
        // Using INF (infinity) which can cause json_encode to return false
        Cache::shouldReceive('get')
            ->with('openId')
            ->andReturn((object) [
                'issuer' => INF, // This can cause json_encode to fail
            ]);

        // Create a valid JWK object
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

        // Expect the JwksInvalidException with message about JSON encoding
        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('Failed to encode JWT payload.');

        // Call the method
        SingPassJwtService::generateClientAssertion($jwk, 'mock-code', 'test-client-id');
    }
}
