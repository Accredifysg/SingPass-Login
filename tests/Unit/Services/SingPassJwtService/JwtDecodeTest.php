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
use Jose\Component\Signature\JWS;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RuntimeException;

class JwtDecodeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_jwt_decode_throws_when_payload_is_empty(): void
    {
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid'])->all();
        $keySet = JWKFactory::createFromValues(['keys' => [$newKey]]);
        $key = $keySet->get('test-kid');
        $this->assertInstanceOf(JWK::class, $key);

        $payload = json_encode(['sub' => '1234567890', 'iat' => Carbon::now()->timestamp]) ?: '{}';
        $jwt = $this->createMockJWT($key, $payload);
        $jwkSet = JWKSet::createFromKeyData(['keys' => [$key->all()]]);

        $jwsWithNullPayload = Mockery::mock(JWS::class);
        $jwsWithNullPayload->shouldReceive('getPayload')->andReturn(null);

        Mockery::mock('overload:'.JWSLoader::class)
            ->shouldReceive('loadAndVerifyWithKey')
            ->once()
            ->andReturn($jwsWithNullPayload);

        $this->expectException(JwtDecodeFailedException::class);
        $this->expectExceptionMessage('JWT payload is empty.');

        (new JwtService)->jwtDecode($jwt, $jwkSet);
    }

    public function test_jwt_decode_throws_when_payload_is_not_a_json_object(): void
    {
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid'])->all();
        $keySet = JWKFactory::createFromValues(['keys' => [$newKey]]);
        $key = $keySet->get('test-kid');
        $this->assertInstanceOf(JWK::class, $key);

        $jwt = $this->createMockJWT($key, '"hello"');

        $jwkSet = JWKSet::createFromKeyData(['keys' => [$key->all()]]);

        $this->expectException(JwtDecodeFailedException::class);
        $this->expectExceptionMessage('JWT payload is not a valid JSON object.');

        (new JwtService)->jwtDecode($jwt, $jwkSet);
    }

    public function test_jwt_decode_throws_when_kid_header_is_not_string(): void
    {
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid'])->all();
        $keySet = JWKFactory::createFromValues(['keys' => [$newKey]]);
        $key = $keySet->get('test-kid');
        $this->assertInstanceOf(JWK::class, $key);

        $algorithmManager = new AlgorithmManager([
            new ES256,
        ]);
        $jwsBuilder = new JWSBuilder($algorithmManager);
        $payload = json_encode(['sub' => 'x']) ?: '{}';
        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($key, [
                'alg' => 'ES256',
                'kid' => 999,
            ])
            ->build();
        $serializer = new JwsCompactSerializer;
        $jwt = $serializer->serialize($jws, 0);

        $jwkSet = JWKSet::createFromKeyData(['keys' => [$key->all()]]);

        $this->expectException(JwtDecodeFailedException::class);
        $this->expectExceptionMessage('JWT KID header is missing or invalid.');

        (new JwtService)->jwtDecode($jwt, $jwkSet);
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
