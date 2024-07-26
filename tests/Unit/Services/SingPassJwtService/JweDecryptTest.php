<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services\SingPassJwtService;

use Accredifysg\SingPassLogin\Exceptions\JweDecryptionFailedException;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\ECKey;
use Jose\Component\Core\Util\RSAKey;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256CBCHS512;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A256KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use Jose\Component\KeyManagement\JWKFactory;

class JweDecryptTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        // Set up default configuration values
        $app['config']->set('services.singpass-login.encryption_key', 'test-private-key');
    }

    public function test_jwe_decrypt_success()
    {
        // Create new key
        $key = JWKFactory::createECKey('P-521');
        $pem = ECKey::convertToPEM($key);

        // Mock configuration values
        Config::set('services.singpass-login.encryption_key', $pem);

        // Create a mock JWE token
        $payload = 'test-payload';
        $jwe = $this->createMockJWE($key, $payload);

        // Call the method
        $decryptedPayload = SingPassJwtService::jweDecrypt($jwe);

        // Assert the decrypted payload is correct
        $this->assertEquals($payload, $decryptedPayload);
    }

    public function test_jwe_decrypt_failure_bad_token()
    {
        // Mock configuration values
        $privateKey = str_replace('\\n', "\n", 'test-private-key');
        Config::set('services.singpass-login.encryption_key', $privateKey);

        // Create an invalid JWE token
        $invalidJwe = 'invalid-jwe-token';

        // Expect the JweDecryptionFailedException to be thrown
        $this->expectException(JweDecryptionFailedException::class);

        // Call the method
        SingPassJwtService::jweDecrypt($invalidJwe);
    }

    public function test_jwe_decrypt_failure()
    {
        // Create new key
        $key = JWKFactory::createECKey('P-521');
        $key2 = JWKFactory::createECKey('P-521');
        $pem = ECKey::convertToPEM($key);

        // Mock configuration values
        Config::set('services.singpass-login.encryption_key', $pem);

        // Create a mock JWE token
        $payload = 'test-payload';
        $jwe = $this->createMockJWE($key2, $payload);

        // Expect the JweDecryptionFailedException to be thrown
        $this->expectException(JweDecryptionFailedException::class);

        // Call the method
        SingPassJwtService::jweDecrypt($jwe);
    }

    private function createMockJWE(JWK $key, string $payload): string
    {
        $algorithmManager = new AlgorithmManager([
            new ECDHESA256KW(),
            new A256KW(),
            new A256CBCHS512(),
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
            ])
            ->addRecipient($key)
            ->build();

        $serializer = new CompactSerializer();

        return $serializer->serialize($jwe, 0);
    }
}
