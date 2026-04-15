<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services;

use Accredifysg\SingPassLogin\Services\DPoPService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use InvalidArgumentException;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;

class DPoPServiceTest extends TestCase
{
    private DPoPService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ndi.dpop_signing_algorithm', 'ES256');
        $this->service = new DPoPService;
    }

    public function test_generate_key_pair_creates_ec_p256_key(): void
    {
        $key = $this->service->generateKeyPair();

        $this->assertInstanceOf(JWK::class, $key);
        $this->assertEquals('EC', $key->get('kty'));
        $this->assertEquals('P-256', $key->get('crv'));
        $this->assertTrue($key->has('d'));
    }

    public function test_rejects_unsupported_signing_algorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported DPoP signing algorithm');

        config()->set('ndi.dpop_signing_algorithm', 'RS256');
        $this->service->generateKeyPair();
    }

    public function test_rejects_non_canonical_algorithm_casing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        config()->set('ndi.dpop_signing_algorithm', 'es256');
        $this->service->generateKeyPair();
    }

    public function test_es384_key_and_proof_use_configured_algorithm(): void
    {
        config()->set('ndi.dpop_signing_algorithm', 'ES384');
        $service = new DPoPService;
        $key = $service->generateKeyPair();

        $this->assertEquals('P-384', $key->get('crv'));

        $proofJwt = $service->generateProofJwt($key, 'POST', 'https://example.com/token');
        $serializer = new JwsCompactSerializer;
        $header = $serializer->unserialize($proofJwt)->getSignature(0)->getProtectedHeader();

        $this->assertEquals('ES384', $header['alg']);
    }

    public function test_generate_key_pair_creates_unique_keys(): void
    {
        $key1 = $this->service->generateKeyPair();
        $key2 = $this->service->generateKeyPair();

        $this->assertNotEquals($key1->get('x'), $key2->get('x'));
    }

    public function test_generate_proof_jwt_structure(): void
    {
        $key = $this->service->generateKeyPair();
        $proofJwt = $this->service->generateProofJwt($key, 'POST', 'https://example.com/token');

        $serializer = new JwsCompactSerializer;
        $jws = $serializer->unserialize($proofJwt);

        // Verify header
        $header = $jws->getSignature(0)->getProtectedHeader();
        $this->assertEquals('ES256', $header['alg']);
        $this->assertEquals('dpop+jwt', $header['typ']);
        $this->assertArrayHasKey('jwk', $header);
        $this->assertEquals('EC', $header['jwk']['kty']);
        $this->assertArrayNotHasKey('d', $header['jwk']);

        // Verify payload
        $payload = json_decode($jws->getPayload() ?: '{}', true);
        $this->assertEquals('POST', $payload['htm']);
        $this->assertEquals('https://example.com/token', $payload['htu']);
        $this->assertArrayHasKey('jti', $payload);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayNotHasKey('ath', $payload);

        $this->assertLessThanOrEqual(120, $payload['exp'] - $payload['iat']);
    }

    public function test_generate_proof_jwt_with_ath(): void
    {
        $key = $this->service->generateKeyPair();
        $proofJwt = $this->service->generateProofJwt($key, 'GET', 'https://example.com/userinfo', 'test-ath-value');

        $serializer = new JwsCompactSerializer;
        $jws = $serializer->unserialize($proofJwt);

        $payload = json_decode($jws->getPayload() ?: '{}', true);
        $this->assertEquals('GET', $payload['htm']);
        $this->assertEquals('https://example.com/userinfo', $payload['htu']);
        $this->assertEquals('test-ath-value', $payload['ath']);
    }

    public function test_generate_proof_jwt_unique_jti(): void
    {
        $key = $this->service->generateKeyPair();
        $proof1 = $this->service->generateProofJwt($key, 'POST', 'https://example.com/token');
        $proof2 = $this->service->generateProofJwt($key, 'POST', 'https://example.com/token');

        $serializer = new JwsCompactSerializer;
        $payload1 = json_decode($serializer->unserialize($proof1)->getPayload() ?: '{}', true);
        $payload2 = json_decode($serializer->unserialize($proof2)->getPayload() ?: '{}', true);

        $this->assertNotEquals($payload1['jti'], $payload2['jti']);
    }

    public function test_compute_access_token_hash(): void
    {
        $ath = $this->service->computeAccessTokenHash('test-access-token');

        $this->assertNotEmpty($ath);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $ath);
    }

    public function test_store_and_retrieve_key_for_state(): void
    {
        $key = $this->service->generateKeyPair();
        $state = 'test-state-'.uniqid();

        $this->service->storeKeyForState($state, $key);

        $retrievedKey = $this->service->retrieveKeyForState($state);

        $this->assertInstanceOf(JWK::class, $retrievedKey);
        $this->assertEquals($key->get('x'), $retrievedKey->get('x'));
        $this->assertEquals($key->get('y'), $retrievedKey->get('y'));
        $this->assertEquals($key->get('d'), $retrievedKey->get('d'));
    }

    public function test_retrieve_key_returns_null_for_unknown_state(): void
    {
        $result = $this->service->retrieveKeyForState('nonexistent-state');

        $this->assertNull($result);
    }

    public function test_clear_key_for_state(): void
    {
        $key = $this->service->generateKeyPair();
        $state = 'test-state-'.uniqid();

        $this->service->storeKeyForState($state, $key);
        $this->assertNotNull($this->service->retrieveKeyForState($state));

        $this->service->clearKeyForState($state);
        $this->assertNull($this->service->retrieveKeyForState($state));
    }
}
