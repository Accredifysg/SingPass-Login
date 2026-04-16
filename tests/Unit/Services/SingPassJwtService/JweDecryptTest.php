<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services\SingPassJwtService;

use Accredifysg\SingPassLogin\Exceptions\JweDecryptionFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Services\JwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256CBCHS512;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A256KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\JWE;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\JWELoader;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use Jose\Component\KeyManagement\JWKFactory;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class JweDecryptTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_jwe_decrypt_success(): void
    {
        // Create new key
        $key = JWKFactory::createECKey('P-521', ['kid' => 'test-kid']);
        $jwks = json_encode(['keys' => [$key->jsonSerialize()]]);

        // Mock configuration values
        Config::set('ndi.private_jwks', $jwks);

        // Create a mock JWE token
        $payload = 'test-payload';
        $jwe = $this->createMockJWE($key, $payload);

        // Call the method
        $decryptedPayload = (new JwtService)->jweDecrypt($jwe);

        // Assert the decrypted payload is correct
        $this->assertEquals($payload, $decryptedPayload);
    }

    public function test_jwe_decrypt_failure_missing_kid(): void
    {
        // Create new key
        $key = JWKFactory::createECKey('P-521', ['kid' => 'test-wrong-kid']);
        $jwks = json_encode(['keys' => [$key->jsonSerialize()]]);

        // Mock configuration values
        Config::set('ndi.private_jwks', $jwks);

        // Create a mock JWE token
        $payload = 'test-payload';
        $jwe = $this->createMockJWE($key, $payload);

        // Expect the JweDecryptionFailedException to be thrown
        $this->expectException(JweDecryptionFailedException::class);
        $this->expectExceptionMessage('KID specified not found in JWKS.');

        // Call the method
        (new JwtService)->jweDecrypt($jwe);
    }

    public function test_jwe_decrypt_failure_invalid_jwe(): void
    {
        // Create an invalid JWE token
        $invalidJwe = 'invalid-jwe-token';

        // Expect the JweDecryptionFailedException to be thrown
        $this->expectException(JweDecryptionFailedException::class);
        $this->expectExceptionMessage('JWE invalid.');

        // Call the method
        (new JwtService)->jweDecrypt($invalidJwe);
    }

    public function test_jwe_decrypt_failure_invalid_private_jwks(): void
    {
        // Create new key
        $key = JWKFactory::createECKey('P-521', ['kid' => 'test-wrong-kid']);
        $jwks = json_encode(['keys' => [$key->jsonSerialize()]]);

        // Mock configuration values
        Config::set('ndi.private_jwks', $jwks. 1);

        // Create a mock JWE token
        $payload = 'test-payload';
        $jwe = $this->createMockJWE($key, $payload);

        // Expect the JwksInvalidException to be thrown
        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('JWKS is an invalid JSON string.');

        // Call the method
        (new JwtService)->jweDecrypt($jwe);
    }

    public function test_jwe_decrypt_failure_invalid_kid_key(): void
    {
        // Create new key
        $key = JWKFactory::createECKey('P-521', ['kid' => 'test-kid']);
        $wrongKey = JWKFactory::createECKey('P-521', ['kid' => 'test-kid']);
        $jwks = json_encode(['keys' => [$wrongKey->jsonSerialize()]]);

        // Mock configuration values
        Config::set('ndi.private_jwks', $jwks);

        // Create a mock JWE token
        $payload = 'test-payload';
        $jwe = $this->createMockJWE($key, $payload);

        $this->expectException(JweDecryptionFailedException::class);
        $this->expectExceptionMessage('JWE cannot be decrypted with KID specified.');

        (new JwtService)->jweDecrypt($jwe);
    }

    public function test_jwe_decrypt_throws_when_kid_header_is_not_string(): void
    {
        $key = JWKFactory::createECKey('P-521', ['kid' => 'test-kid']);
        $jwks = json_encode(['keys' => [$key->jsonSerialize()]]);
        Config::set('ndi.private_jwks', $jwks);

        $jwe = $this->createMockJWE($key, 'test-payload', 123);

        $this->expectException(JweDecryptionFailedException::class);
        $this->expectExceptionMessage('JWE KID header is missing or invalid.');

        (new JwtService)->jweDecrypt($jwe);
    }

    public function test_jwe_decrypt_throws_when_private_jwks_is_not_string(): void
    {
        $key = JWKFactory::createECKey('P-521', ['kid' => 'test-kid']);
        Config::set('ndi.private_jwks', null);

        $jwe = $this->createMockJWE($key, 'test-payload');

        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('Private JWKS not set or invalid.');

        (new JwtService)->jweDecrypt($jwe);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_jwe_decrypt_throws_when_decrypted_payload_is_null(): void
    {
        $key = JWKFactory::createECKey('P-521', ['kid' => 'test-kid']);
        $jwks = json_encode(['keys' => [$key->jsonSerialize()]]);
        Config::set('ndi.private_jwks', $jwks);
        $jwe = $this->createMockJWE($key, 'test-payload');

        $jweWithNullPayload = Mockery::mock(JWE::class);
        $jweWithNullPayload->shouldReceive('getPayload')->andReturn(null);

        Mockery::mock('overload:'.JWELoader::class)
            ->shouldReceive('loadAndDecryptWithKey')
            ->once()
            ->andReturn($jweWithNullPayload);

        $this->expectException(JweDecryptionFailedException::class);
        $this->expectExceptionMessage('JWE payload is empty.');

        (new JwtService)->jweDecrypt($jwe);
    }

    private function createMockJWE(JWK $key, string $payload, string|int $kid = 'test-kid'): string
    {
        $algorithmManager = new AlgorithmManager([
            new ECDHESA256KW,
            new A256KW,
            new A256CBCHS512,
        ]);

        $jweBuilder = new JWEBuilder(
            $algorithmManager,
        );

        $jwe = $jweBuilder
            ->create()
            ->withPayload($payload)
            ->withSharedProtectedHeader([
                'alg' => 'ECDH-ES+A256KW',
                'enc' => 'A256CBC-HS512',
                'kid' => $kid,
            ])
            ->addRecipient($key)
            ->build();

        $serializer = new CompactSerializer;

        return $serializer->serialize($jwe, 0);
    }
}
