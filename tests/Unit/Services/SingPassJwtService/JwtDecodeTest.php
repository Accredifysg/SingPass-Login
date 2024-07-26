<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Services\SingPassJwtService;

use Accredifysg\SingPassLogin\Exceptions\JwtDecodeFailedException;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Carbon\Carbon;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;

class JwtDecodeTest extends TestCase
{
    public function test_jwt_decode_success()
    {
        // Create new key
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid'])->all();

        // Mock JWK set
        $keySet = [
            'keys' => [
                $newKey,
            ],
        ];

        // Create a JWK object
        $keySet = JWKFactory::createFromValues($keySet);
        $key = $keySet->get('test-kid');

        // Create a mock JWT token
        $payload = json_encode(['sub' => '1234567890', 'name' => 'John Doe', 'iat' => Carbon::now()->timestamp]);
        $jwt = $this->createMockJWT($key, $payload);

        // Call the method
        $decodedPayload = SingPassJwtService::jwtDecode($jwt, $keySet);

        // Assert the decoded payload is correct
        $this->assertEquals(json_decode($payload, false), $decodedPayload);
    }

    public function test_jwt_decode_failure()
    {
        // Mock JWK set
        // Create new key
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid'])->all();

        // Mock JWK set
        $keySet = [
            'keys' => [
                $newKey,
            ],
        ];

        // Create an invalid JWT token
        $invalidJwt = 'invalid.jwt.token';

        // Expect the JwtDecodeFailedException to be thrown
        $this->expectException(JwtDecodeFailedException::class);
        $this->expectExceptionMessage('JWT supplied is invalid.');

        // Call the method
        SingPassJwtService::jwtDecode($invalidJwt, $keySet);
    }

    public function test_jwt_decode_failure_invalid_kid()
    {
        // Create new key
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid'])->all();
        $wrongKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid-kid'])->all();

        // Mock JWK set
        $keySet = [
            'keys' => [
                $newKey,
            ],
        ];

        $wrongKeySet = [
            'keys' => [
                $wrongKey,
            ],
        ];

        // Create a JWK object
        $keySet = JWKFactory::createFromValues($keySet);
        $key = $keySet->get('test-kid');
        $wrongKeySet = JWKFactory::createFromValues($wrongKeySet);

        // Create a mock JWT token
        $payload = json_encode(['sub' => '1234567890', 'name' => 'John Doe', 'iat' => Carbon::now()->timestamp]);
        $jwt = $this->createMockJWT($key, $payload);

        // Expect the JwtDecodeFailedException to be thrown
        $this->expectException(JwtDecodeFailedException::class);
        $this->expectExceptionMessage('Keyset does not contain KID from JWT.');

        // Call the method
        SingPassJwtService::jwtDecode($jwt, $wrongKeySet);
    }

    private function createMockJWT(JWK $key, string $payload): string
    {
        $algorithmManager = new AlgorithmManager([
            new ES256(),
        ]);

        $jwsBuilder = new JWSBuilder(
            $algorithmManager
        );

        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($key, [
                'alg' => 'ES256',
                'kid' => 'test-kid',
            ])
            ->build();

        $serializer = new JwsCompactSerializer();

        return $serializer->serialize($jws, 0);
    }
}
