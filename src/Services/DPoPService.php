<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Illuminate\Support\Str;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;

final class DPoPService implements DPoPServiceInterface
{
    public function generateKeyPair(): JWK
    {
        return JWKFactory::createECKey('P-256');
    }

    public function generateProofJwt(JWK $privateKey, string $htm, string $htu, ?string $ath = null): string
    {
        $publicKey = $privateKey->toPublic();

        $algorithmManager = new AlgorithmManager([new ES256]);
        $jwsBuilder = new JWSBuilder($algorithmManager);

        $payload = [
            'jti' => Str::uuid(),
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
            'alg' => 'ES256',
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
}
