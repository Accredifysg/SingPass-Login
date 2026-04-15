<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\Algorithm\SignatureAlgorithm;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use JsonException;

final class DPoPService implements DPoPServiceInterface
{
    public function generateKeyPair(): JWK
    {
        return JWKFactory::createECKey(self::curveForAlgorithm($this->resolveSigningAlgorithm()));
    }

    public function generateProofJwt(JWK $privateKey, string $htm, string $htu, ?string $ath = null): string
    {
        $signingAlgorithm = $this->resolveSigningAlgorithm();
        $publicKey = $privateKey->toPublic();

        $algorithmManager = new AlgorithmManager([self::signatureAlgorithmInstance($signingAlgorithm)]);
        $jwsBuilder = new JWSBuilder($algorithmManager);

        $payload = [
            'jti' => Str::uuid()->toString(),
            'htm' => $htm,
            'htu' => $htu,
            'iat' => time(),
            'exp' => time() + 120,
        ];

        if ($ath !== null) {
            $payload['ath'] = $ath;
        }

        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $header = [
            'alg' => $signingAlgorithm,
            'typ' => 'dpop+jwt',
            'jwk' => $publicKey->jsonSerialize(),
        ];

        $jws = $jwsBuilder->create()
            ->withPayload($encodedPayload)
            ->addSignature($privateKey, $header)
            ->build();

        return (new JwsCompactSerializer)->serialize($jws, 0);
    }

    public function computeAccessTokenHash(string $accessToken): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $accessToken, true)), '+/', '-_'), '=');
    }

    public function storeKeyForState(string $state, JWK $key): void
    {
        session()->put("dpop_key_{$state}", json_encode($key->jsonSerialize()));
    }

    public function retrieveKeyForState(string $state): ?JWK
    {
        $keyJson = session()->get("dpop_key_{$state}");

        if ($keyJson === null) {
            return null;
        }

        return new JWK(json_decode($keyJson, true));
    }

    public function clearKeyForState(string $state): void
    {
        session()->forget("dpop_key_{$state}");
    }

    /**
     * @return 'ES256'|'ES384'|'ES512'
     *
     * @throws JsonException
     */
    private function resolveSigningAlgorithm(): string
    {
        $algorithm = config('ndi.dpop_signing_algorithm');

        return match ($algorithm) {
            'ES256', 'ES384', 'ES512' => $algorithm,
            default => throw new InvalidArgumentException(
                sprintf(
                    'Unsupported DPoP signing algorithm %s. Supported values: ES256, ES384, ES512.',
                    json_encode($algorithm, JSON_THROW_ON_ERROR)
                )
            ),
        };
    }

    /**
     * @param  'ES256'|'ES384'|'ES512'  $algorithm
     * @return 'P-256'|'P-384'|'P-521'
     */
    private static function curveForAlgorithm(string $algorithm): string
    {
        return match ($algorithm) {
            'ES256' => 'P-256',
            'ES384' => 'P-384',
            'ES512' => 'P-521',
        };
    }

    /**
     * @param  'ES256'|'ES384'|'ES512'  $algorithm
     */
    private static function signatureAlgorithmInstance(string $algorithm): SignatureAlgorithm
    {
        return match ($algorithm) {
            'ES256' => new ES256,
            'ES384' => new ES384,
            'ES512' => new ES512,
        };
    }
}
