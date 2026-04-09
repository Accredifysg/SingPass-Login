<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services\SingPassJwtService;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
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
        $app['config']->set('singpass-login.client_id', 'test-client-id');
        $app['config']->set('singpass-login.signing_kid', 'test-signing-kid');
    }

    public function test_generate_client_assertion_success(): void
    {
        Cache::shouldReceive('get')
            ->with('openId')
            ->andReturn(new OpenIdConfigurationDto(
                issuer: 'https://example.com',
                authorizationEndpoint: 'https://example.com/auth',
                tokenEndpoint: 'https://example.com/token',
                userinfoEndpoint: 'https://example.com/userinfo',
                jwksUri: 'https://example.com/jwks',
                pushedAuthorizationRequestEndpoint: 'https://example.com/par',
            ));

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

        $clientAssertion = SingPassJwtService::generateClientAssertion($jwk, 'test-client-id');

        $this->assertNotEmpty($clientAssertion);

        $serializer = new JwsCompactSerializer;
        $jws = $serializer->unserialize($clientAssertion);

        $this->assertEquals(1, $jws->countSignatures());

        $payload = json_decode($jws->getPayload() ?: '{}', true);

        $this->assertEquals('test-client-id', $payload['sub']);
        $this->assertEquals('https://example.com', $payload['aud']);
        $this->assertEquals('test-client-id', $payload['iss']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('jti', $payload);
        $this->assertNotEmpty($payload['jti']);
        $this->assertArrayNotHasKey('code', $payload);
    }

    public function test_generate_client_assertion_with_myinfo_client_id(): void
    {
        Cache::shouldReceive('get')
            ->with('openId')
            ->andReturn(new OpenIdConfigurationDto(
                issuer: 'https://example.com',
                authorizationEndpoint: 'https://example.com/auth',
                tokenEndpoint: 'https://example.com/token',
                userinfoEndpoint: 'https://example.com/userinfo',
                jwksUri: 'https://example.com/jwks',
                pushedAuthorizationRequestEndpoint: 'https://example.com/par',
            ));

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

        $clientAssertion = SingPassJwtService::generateClientAssertion($jwk, 'myinfo-client-id');

        $this->assertNotEmpty($clientAssertion);

        $serializer = new JwsCompactSerializer;
        $jws = $serializer->unserialize($clientAssertion);

        $this->assertEquals(1, $jws->countSignatures());

        $payload = json_decode($jws->getPayload() ?: '{}', true);

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
        Cache::shouldReceive('get')
            ->with('openId')
            ->andReturn(new OpenIdConfigurationDto(
                issuer: 'https://example.com',
                authorizationEndpoint: 'https://example.com/auth',
                tokenEndpoint: 'https://example.com/token',
                userinfoEndpoint: 'https://example.com/userinfo',
                jwksUri: 'https://example.com/jwks',
                pushedAuthorizationRequestEndpoint: 'https://example.com/par',
            ));

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

        SingPassJwtService::generateClientAssertion($jwk, 'test-client-id');
    }

    public function test_generate_client_assertion_json_encode_failure(): void
    {
        Cache::shouldReceive('get')
            ->with('openId')
            ->andReturn(new OpenIdConfigurationDto(
                issuer: 'https://example.com',
                authorizationEndpoint: 'https://example.com/auth',
                tokenEndpoint: 'https://example.com/token',
                userinfoEndpoint: 'https://example.com/userinfo',
                jwksUri: 'https://example.com/jwks',
                pushedAuthorizationRequestEndpoint: 'https://example.com/par',
            ));

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

        SingPassJwtService::generateClientAssertion($jwk, "\xB1\x31");
    }
}
