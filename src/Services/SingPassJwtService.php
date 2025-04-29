<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Exceptions\JweDecryptionFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Exceptions\JwtDecodeFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\InvalidClaimException;
use Jose\Component\Checker\IssuedAtChecker;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256CBCHS512;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A256KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\JWELoader;
use Jose\Component\Encryption\JWETokenSupport;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use Jose\Component\Encryption\Serializer\JWESerializerManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use JsonException;
use Symfony\Component\Clock\NativeClock;

final class SingPassJwtService implements SingPassJwtServiceInterface
{
    /**
     * Gets the key to sign the Assertion with based on what is set in the ENV
     */
    public static function getSigningJwk(): JWK
    {
        $jwks = config('singpass-login.private_jwks');

        if ($jwks === null) {
            throw new JwksInvalidException(500, 'Private JWKS not set.');
        }

        try {
            $jwkSets = JWKSet::createFromJson($jwks);
        } catch (Exception) {
            throw new JwksInvalidException(500, 'JWKS JSON Invalid.');
        }

        try {
            $signingKey = $jwkSets->get(config('singpass-login.signing_kid'));
        } catch (Exception) {
            throw new JwksInvalidException(500, 'Signing key not found.');
        }

        return $signingKey;
    }

    /**
     * Generate the client assertion needed to retrieve a token to use for subsequent calls
     */
    public static function generateClientAssertion($jwk, $code): string
    {
        $algorithmManager = new AlgorithmManager([
            new ES512,
        ]);

        $jwsBuilder = new JWSBuilder($algorithmManager);

        $payload = json_encode([
            'sub' => config('singpass-login.client_id'),
            'aud' => Cache::get('openId')->issuer,
            'iss' => config('singpass-login.client_id'),
            'iat' => time(),
            'exp' => time() + 119,
            'code' => $code,
        ]);

        try {
            $jws = $jwsBuilder->create()
                ->withPayload($payload)
                ->addSignature($jwk, [
                    'typ' => 'JWT',
                    'alg' => 'ES512',
                    'kid' => config('singpass-login.signing_kid'),
                ])->build();
        } catch (Exception) {
            throw new JwksInvalidException(500, 'JWKS JSON Invalid.');
        }

        $serializer = new JwsCompactSerializer;

        return $serializer->serialize($jws, 0);
    }

    /**
     * Decrypts the JWE that was returned from SingPass's token endpoint
     *
     * @throws JweDecryptionFailedException
     */
    public function jweDecrypt($jweToken): string
    {
        $algorithmManager = new AlgorithmManager([
            new A256KW,
            new ECDHESA256KW,
            new A256CBCHS512,
        ]);

        $serializerManager = new JWESerializerManager([
            new CompactSerializer,
        ]);

        try {
            $jwe = $serializerManager->unserialize($jweToken);
        } catch (Exception) {
            throw new JweDecryptionFailedException(500, 'JWE invalid.');
        }

        $jweDecrypter = new JWEDecrypter($algorithmManager);

        try {
            $kid = $jwe->getSharedProtectedHeaderParameter('kid');
            $keySet = JWKFactory::createFromJsonObject(config('singpass-login.private_jwks'));
            $key = $keySet->get($kid);
        } catch (InvalidArgumentException) {
            throw new JweDecryptionFailedException(500, 'KID specified not found in JWKS.');
        } catch (JsonException) {
            throw new JwksInvalidException(500, 'JWKS is an invalid JSON string.');
        }

        if ($jweDecrypter->decryptUsingKey($jwe, $key, 0)) {
            $headerCheckerManager = new HeaderCheckerManager([
                new AlgorithmChecker(['ECDH-ES+A256KW']),
            ], [
                new JWETokenSupport,
            ]);

            $jweLoader = new JWELoader($serializerManager, $jweDecrypter, $headerCheckerManager);

            $jwe = $jweLoader->loadAndDecryptWithKey($jweToken, $key, $recipient);

            return $jwe->getPayload();
        }

        throw new JweDecryptionFailedException(500, 'JWE cannot be decrypted with KID specified.');
    }

    /**
     * Decrypts the JWT that was encrypted within the JWE token
     *
     * @throws JwtDecodeFailedException
     */
    public function jwtDecode(string $jwtToken, JWKSet $jwksKeyset): array
    {
        $algorithmManager = new AlgorithmManager([
            new ES256,
        ]);

        $jwsVerifier = new JWSVerifier($algorithmManager);

        $serializerManager = new JWSSerializerManager([
            new JwsCompactSerializer,
        ]);

        try {
            $kid = $serializerManager->unserialize($jwtToken)->getSignature(0)->getProtectedHeaderParameter('kid');
        } catch (InvalidArgumentException) {
            throw new JwtDecodeFailedException(500, 'JWT supplied is invalid.');
        }

        try {
            $key = JWKFactory::createFromKeySet($jwksKeyset, $kid);
        } catch (InvalidArgumentException) {
            throw new JwtDecodeFailedException(500, 'Keyset does not contain KID from JWT.');
        }

        $headerCheckerManager = new HeaderCheckerManager([
            new AlgorithmChecker(['ES256']),
        ], [
            new JWSTokenSupport,
        ]);

        $jwsLoader = new JWSLoader($serializerManager, $jwsVerifier, $headerCheckerManager);

        $jws = $jwsLoader->loadAndVerifyWithKey($jwtToken, $key, $signature);

        return json_decode($jws->getPayload(), true);
    }

    /**
     * Verifies they payload to ensure it is valid
     */
    public function verifyPayload(array $payload): void
    {
        $clock = new NativeClock;

        $claimCheckerManager = new ClaimCheckerManager(
            [
                new AudienceChecker(config('singpass-login.client_id')),
                new IssuedAtChecker($clock, 5),
                new ExpirationTimeChecker($clock, 5),
                new IssuerChecker([config('singpass-login.domain')]),
            ]
        );

        try {
            $claimCheckerManager->check($payload);
        } catch (InvalidClaimException $exception) {
            throw new JwtPayloadException(400, $exception->getMessage());
        }
    }
}
