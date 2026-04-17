<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services\SingPassJwtService;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Services\JwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;

class GetSigningJwkTest extends TestCase
{
    public function test_get_signing_jwk_success(): void
    {
        // Create new key
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid-id'])->all();

        // Mock JWK set
        $keySet = [
            'keys' => [
                $newKey,
            ],
        ];

        // Set up default configuration values
        Config::set('ndi.private_jwks', json_encode($keySet));
        Config::set('ndi.signing_kid', 'test-kid-id');

        // Call the method
        $jwk = JwtService::getSigningJwk();

        // Assert the method returns a JWK object
        $this->assertInstanceOf(JWK::class, $jwk);

        // Assert the JWK object contains the expected values
        $this->assertEquals('test-kid-id', $jwk->get('kid'));
    }

    public function test_get_signing_private_jwk_exception(): void
    {
        // Expect the JwksInvalidException to be thrown
        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('Private JWKS not set.');

        // Call the method
        JwtService::getSigningJwk();
    }

    public function test_get_signing_jwk_throws_when_signing_kid_is_not_string(): void
    {
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid-id'])->all();
        Config::set('ndi.private_jwks', json_encode(['keys' => [$newKey]]));
        Config::set('ndi.signing_kid', null);

        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('Signing KID not set or invalid.');

        JwtService::getSigningJwk();
    }

    public function test_get_signing_jwk_invalid_json_exception(): void
    {
        // Set up default configuration values
        Config::set('ndi.private_jwks', '{{}');

        // Expect the JwksInvalidException to be thrown
        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('JWKS JSON Invalid.');

        // Call the method
        JwtService::getSigningJwk();
    }

    public function test_get_signing_jwk_key_not_found_exception(): void
    {
        // Create new key
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'wrong-test-kid-id'])->all();

        // Mock JWK set
        $keySet = [
            'keys' => [
                $newKey,
            ],
        ];

        // Set up default configuration values
        Config::set('ndi.private_jwks', json_encode($keySet));
        Config::set('ndi.signing_kid', 'test-kid-id');

        // Expect the JwksInvalidException to be thrown
        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('Signing key not found.');

        // Call the method
        JwtService::getSigningJwk();
    }
}
