<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Services\SingPassJwtService;

use Accredifysg\SingPassLogin\Exceptions\JwtDecodeFailedException;
use Accredifysg\SingPassLogin\Services\JwtService;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use RuntimeException;

class JwtDecodeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        if (! $app instanceof Application) {
            throw new RuntimeException('Expected application instance.');
        }
        $this->appConfigSet($app, 'singpass-login.client_id', 'test-client-id');
        $this->appConfigSet($app, 'singpass-login.domain', 'test-domain');
    }

    public function test_jwt_decode_success(): void
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
        $this->assertInstanceOf(JWK::class, $key);

        $now = Carbon::now();

        // Create a mock JWT token
        $payload = json_encode(
            [
                'sub' => '1234567890',
                'aud' => config('singpass-login.client_id'),
                'iss' => config('singpass-login.domain'),
                'iat' => $now->timestamp,
                'exp' => $now->addMinutes(10)->timestamp,
            ]
        ) ?: '{}';
        $jwt = $this->createMockJWT($key, $payload);

        // Create JWKSet from keySet
        $jwkSet = JWKSet::createFromKeyData(['keys' => [$key->all()]]);

        // Call the method
        $decodedPayload = (new JwtService)->jwtDecode($jwt, $jwkSet);

        $expected = json_decode($payload, true);
        $this->assertIsArray($expected);
        $this->assertEquals($expected, $decodedPayload);
    }

    public function test_jwt_decode_failure(): void
    {
        // Mock JWK set
        // Create new key
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid'])->all();

        // Mock JWK set
        $keySet = JWKSet::createFromKeyData([
            'keys' => [
                $newKey,
            ],
        ]);

        // Create an invalid JWT token
        $invalidJwt = 'invalid.jwt.token';

        // Expect the JwtDecodeFailedException to be thrown
        $this->expectException(JwtDecodeFailedException::class);
        $this->expectExceptionMessage('JWT supplied is invalid.');

        // Call the method
        (new JwtService)->jwtDecode($invalidJwt, $keySet);
    }

    public function test_jwt_decode_failure_invalid_kid(): void
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
        $this->assertInstanceOf(JWK::class, $key);
        $wrongKeySet = JWKFactory::createFromValues($wrongKeySet);
        $wrongKidJwk = $wrongKeySet->get('test-kid-kid');
        $this->assertInstanceOf(JWK::class, $wrongKidJwk);

        // Create a mock JWT token
        $payload = json_encode(['sub' => '1234567890', 'name' => 'John Doe', 'iat' => Carbon::now()->timestamp]) ?: '{}';
        $jwt = $this->createMockJWT($key, $payload);

        // Create JWKSet from wrongKeySet
        $wrongJwkSet = JWKSet::createFromKeyData(['keys' => [$wrongKidJwk->all()]]);

        // Expect the JwtDecodeFailedException to be thrown
        $this->expectException(JwtDecodeFailedException::class);
        $this->expectExceptionMessage('Keyset does not contain KID from JWT.');

        // Call the method
        (new JwtService)->jwtDecode($jwt, $wrongJwkSet);
    }

    private function createMockJWT(JWK $key, string $payload): string
    {
        $algorithmManager = new AlgorithmManager([
            new ES256,
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

        $serializer = new JwsCompactSerializer;

        return $serializer->serialize($jws, 0);
    }
}
