<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services\SingPassJwtService;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Services\JwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use RuntimeException;

class GenerateClientAssertionTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        if (! $app instanceof Application) {
            throw new RuntimeException('Expected application instance.');
        }
        $this->appConfigSet($app, 'singpass-login.client_id', 'test-client-id');
        $this->appConfigSet($app, 'ndi.signing_kid', 'test-signing-kid');
    }

    public function test_generate_client_assertion_success(): void
    {
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

        $clientAssertion = JwtService::generateClientAssertion($jwk, 'test-client-id', 'https://example.com');

        $this->assertNotEmpty($clientAssertion);

        $serializer = new JwsCompactSerializer;
        $jws = $serializer->unserialize($clientAssertion);

        $this->assertEquals(1, $jws->countSignatures());

        $payload = json_decode($jws->getPayload() ?: '{}', true);
        $this->assertIsArray($payload);

        $this->assertEquals('test-client-id', $payload['sub']);
        $this->assertEquals('https://example.com', $payload['aud']);
        $this->assertEquals('test-client-id', $payload['iss']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('jti', $payload);
        $this->assertNotEmpty($payload['jti']);
        $this->assertArrayNotHasKey('code', $payload);

        $this->assertEquals('ES512', $jws->getSignature(0)->getProtectedHeaderParameter('alg'));
    }

    public function test_it_uses_es256_for_p256_signing_key(): void
    {
        $jwk = JWKFactory::createECKey('P-256', [
            'kid' => 'test-signing-kid',
            'use' => 'sig',
        ]);

        $clientAssertion = JwtService::generateClientAssertion($jwk, 'test-client-id', 'https://example.com');

        $serializer = new JwsCompactSerializer;
        $jws = $serializer->unserialize($clientAssertion);

        $this->assertEquals('ES256', $jws->getSignature(0)->getProtectedHeaderParameter('alg'));
    }

    public function test_it_uses_es384_for_p384_signing_key(): void
    {
        $jwk = JWKFactory::createECKey('P-384', [
            'kid' => 'test-signing-kid',
            'use' => 'sig',
        ]);

        $clientAssertion = JwtService::generateClientAssertion($jwk, 'test-client-id', 'https://example.com');

        $serializer = new JwsCompactSerializer;
        $jws = $serializer->unserialize($clientAssertion);

        $this->assertEquals('ES384', $jws->getSignature(0)->getProtectedHeaderParameter('alg'));
    }

    public function test_generate_client_assertion_with_myinfo_client_id(): void
    {
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

        $clientAssertion = JwtService::generateClientAssertion($jwk, 'myinfo-client-id', 'https://example.com');

        $this->assertNotEmpty($clientAssertion);

        $serializer = new JwsCompactSerializer;
        $jws = $serializer->unserialize($clientAssertion);

        $this->assertEquals(1, $jws->countSignatures());

        $payload = json_decode($jws->getPayload() ?: '{}', true);
        $this->assertIsArray($payload);

        $this->assertEquals('myinfo-client-id', $payload['sub']);
        $this->assertEquals('https://example.com', $payload['aud']);
        $this->assertEquals('myinfo-client-id', $payload['iss']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('jti', $payload);
        $this->assertArrayNotHasKey('code', $payload);
    }

    public function test_generate_client_assertion_jwk_failure(): void
    {
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

        $this->expectException(JwksInvalidException::class);

        JwtService::generateClientAssertion($jwk, 'test-client-id', 'https://example.com');
    }

    public function test_generate_client_assertion_json_encode_failure(): void
    {
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

        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('Failed to encode JWT payload.');

        JwtService::generateClientAssertion($jwk, "\xB1\x31", 'https://example.com');
    }

    public function test_generate_client_assertion_throws_when_signing_kid_is_not_string(): void
    {
        $jwk = JWKFactory::createECKey('P-256', [
            'kid' => 'test-signing-kid',
            'use' => 'sig',
        ]);

        Config::set('ndi.signing_kid', null);

        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('Signing KID not set or invalid.');

        JwtService::generateClientAssertion($jwk, 'test-client-id', 'https://example.com');
    }

    public function test_generate_client_assertion_throws_when_ec_crv_is_not_string(): void
    {
        $jwk = new JWK([
            'kty' => 'EC',
            'crv' => 123,
            'x' => 'AQ',
            'y' => 'Ag',
            'd' => 'Ag',
            'kid' => 'test-signing-kid',
        ]);

        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('Signing key must be an EC key with a crv (curve) parameter.');

        JwtService::generateClientAssertion($jwk, 'test-client-id', 'https://example.com');
    }

    public function test_generate_client_assertion_wraps_signing_failure_as_jwks_invalid(): void
    {
        $jwk = new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'd' => str_repeat('x', 43),
            'x' => str_repeat('x', 43),
            'y' => str_repeat('x', 43),
            'kid' => 'test-signing-kid',
        ]);

        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('JWKS JSON Invalid.');

        JwtService::generateClientAssertion($jwk, 'test-client-id', 'https://example.com');
    }
}
