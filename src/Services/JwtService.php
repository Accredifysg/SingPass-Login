<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Exceptions\JweDecryptionFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Exceptions\JwtDecodeFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Interfaces\JwtServiceInterface;
use Accredifysg\SingPassLogin\Support\SingPassLog;
use Accredifysg\SingPassLogin\Support\TypeNarrow;
use Exception;
use Illuminate\Support\Str;
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
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\A256KW;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\JWELoader;
use Jose\Component\Encryption\JWETokenSupport;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use Jose\Component\Encryption\Serializer\JWESerializerManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use JsonException;
use Symfony\Component\Clock\NativeClock;

final class JwtService implements JwtServiceInterface
{
    /**
     * Gets the key to sign the Assertion with based on what is set in the ENV
     */
    public static function getSigningJwk(): JWK
    {
        $jwks = config('ndi.private_jwks');

        if (! is_string($jwks)) {
            throw new JwksInvalidException(500, 'Private JWKS not set.');
        }

        try {
            $jwkSets = JWKSet::createFromJson($jwks);
        } catch (Exception) {
            throw new JwksInvalidException(500, 'JWKS JSON Invalid.');
        }

        $kid = config('ndi.signing_kid');
        if (! is_string($kid)) {
            throw new JwksInvalidException(500, 'Signing KID not set or invalid.');
        }

        try {
            $signingKey = $jwkSets->get($kid);
        } catch (Exception) {
            throw new JwksInvalidException(500, 'Signing key not found.');
        }

        return $signingKey;
    }

    /**
     * Generate the client assertion needed to authenticate with the provider API.
     *
     * The JWS algorithm matches the signing key curve (ES256 for P-256, ES384 for P-384,
     * ES512 for P-521). SingPass accepts ES256/ES384/ES512; using the wrong algorithm for
     * your key causes PAR/token requests to fail (often as a generic bad_request).
     */
    public static function generateClientAssertion(JWK $jwk, string $clientId, string $audience): string
    {
        $algName = self::clientAssertionAlgorithmForEcJwk($jwk);
        $signer = match ($algName) {
            'ES256' => new ES256,
            'ES384' => new ES384,
            'ES512' => new ES512,
        };

        $algorithmManager = new AlgorithmManager([$signer]);

        $jwsBuilder = new JWSBuilder($algorithmManager);

        $payload = json_encode([
            'sub' => $clientId,
            'aud' => $audience,
            'iss' => $clientId,
            'iat' => time(),
            'exp' => time() + 119,
            'jti' => Str::uuid()->toString(),
        ]);

        if ($payload === false) {
            throw new JwksInvalidException(500, 'Failed to encode JWT payload.');
        }

        $signingKid = config('ndi.signing_kid');
        if (! is_string($signingKid)) {
            throw new JwksInvalidException(500, 'Signing KID not set or invalid.');
        }

        try {
            $jws = $jwsBuilder->create()
                ->withPayload($payload)
                ->addSignature($jwk, [
                    'typ' => 'JWT',
                    'alg' => $algName,
                    'kid' => $signingKid,
                ])->build();
        } catch (Exception) {
            throw new JwksInvalidException(500, 'JWKS JSON Invalid.');
        }

        $serializer = new JwsCompactSerializer;

        return $serializer->serialize($jws, 0);
    }

    /**
     * Map EC curve to OIDC private_key_jwt signing algorithm (SingPass-supported set).
     *
     *
     * @return 'ES256'|'ES384'|'ES512'
     */
    private static function clientAssertionAlgorithmForEcJwk(JWK $jwk): string
    {
        $crv = $jwk->get('crv');

        if (! is_string($crv)) {
            throw new JwksInvalidException(500, 'Signing key must be an EC key with a crv (curve) parameter.');
        }

        return match ($crv) {
            'P-256' => 'ES256',
            'P-384' => 'ES384',
            'P-521' => 'ES512',
            default => throw new JwksInvalidException(500, 'Unsupported EC curve for client assertion: '.$crv),
        };
    }

    /**
     * Decrypts the JWE that was returned from SingPass's token endpoint
     *
     * @throws JweDecryptionFailedException
     */
    public function jweDecrypt(string $jweToken): string
    {
        SingPassLog::info('Decrypting JWE token');
        $algorithmManager = new AlgorithmManager([
            new A256KW,
            new ECDHESA256KW,
            new A256CBCHS512,
            new A256GCM,
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
            if (! is_string($kid)) {
                throw new JweDecryptionFailedException(500, 'JWE KID header is missing or invalid.');
            }

            $privateJwks = config('ndi.private_jwks');
            if (! is_string($privateJwks)) {
                throw new JwksInvalidException(500, 'Private JWKS not set or invalid.');
            }

            $keySet = JWKFactory::createFromJsonObject($privateJwks);
            $key = $keySet->get($kid);
            if (! $key instanceof JWK) {
                throw new JweDecryptionFailedException(500, 'JWE decryption key is invalid.');
            }
        } catch (InvalidArgumentException) {
            throw new JweDecryptionFailedException(500, 'KID specified not found in JWKS.');
        } catch (JsonException) {
            throw new JwksInvalidException(500, 'JWKS is an invalid JSON string.');
        }

        if ($jweDecrypter->decryptUsingKey($jwe, $key, 0)) {
            SingPassLog::info('JWE decryption successful');
            $headerCheckerManager = new HeaderCheckerManager([
                new AlgorithmChecker(['ECDH-ES+A256KW']),
            ], [
                new JWETokenSupport,
            ]);

            $jweLoader = new JWELoader($serializerManager, $jweDecrypter, $headerCheckerManager);

            $recipient = 0;
            $jwe = $jweLoader->loadAndDecryptWithKey($jweToken, $key, $recipient);

            $payload = $jwe->getPayload();
            if ($payload === null) {
                throw new JweDecryptionFailedException(500, 'JWE payload is empty.');
            }

            return $payload;
        }

        throw new JweDecryptionFailedException(500, 'JWE cannot be decrypted with KID specified.');
    }

    /**
     * Decrypts the JWT that was encrypted within the JWE token
     *
     * @return array<string, mixed>
     *
     * @throws JwtDecodeFailedException
     */
    public function jwtDecode(string $jwtToken, JWKSet $jwksKeyset): array
    {
        SingPassLog::info('Decoding and verifying JWT signature');
        $algorithmManager = new AlgorithmManager([
            new ES256,
        ]);

        $jwsVerifier = new JWSVerifier($algorithmManager);

        $serializerManager = new JWSSerializerManager([
            new JwsCompactSerializer,
        ]);

        try {
            $kid = $serializerManager->unserialize($jwtToken)->getSignature(0)->getProtectedHeaderParameter('kid');
            if (! is_string($kid)) {
                throw new JwtDecodeFailedException(500, 'JWT KID header is missing or invalid.');
            }
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

        $signature = 0;
        $jws = $jwsLoader->loadAndVerifyWithKey($jwtToken, $key, $signature);

        $payload = $jws->getPayload();
        if ($payload === null) {
            throw new JwtDecodeFailedException(500, 'JWT payload is empty.');
        }

        SingPassLog::info('JWT signature verified successfully');

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            throw new JwtDecodeFailedException(500, 'JWT payload is not a valid JSON object.');
        }

        return TypeNarrow::stringKeyedArray($decoded)
            ?? throw new JwtDecodeFailedException(500, 'JWT payload keys must be strings.');
    }

    /**
     * Verifies the payload to ensure it is valid.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyPayload(array $payload, string $clientId, string $issuerDomain): void
    {
        SingPassLog::info('Verifying ID token claims', [
            'expected_audience' => $clientId,
            'expected_issuer' => $issuerDomain,
            'token_issuer' => $payload['iss'] ?? null,
            'token_audience' => $payload['aud'] ?? null,
        ]);

        $clock = new NativeClock;

        $claimCheckerManager = new ClaimCheckerManager(
            [
                new AudienceChecker($clientId),
                new IssuedAtChecker($clock, 5),
                new ExpirationTimeChecker($clock, 5),
                new IssuerChecker([$issuerDomain]),
            ]
        );

        try {
            $claimCheckerManager->check($payload);
        } catch (InvalidClaimException $exception) {
            SingPassLog::error('ID token claim verification failed', [
                'error' => $exception->getMessage(),
                'expected_audience' => $clientId,
                'expected_issuer' => $issuerDomain,
                'token_issuer' => $payload['iss'] ?? null,
                'token_audience' => $payload['aud'] ?? null,
            ]);

            throw new JwtPayloadException(400, $exception->getMessage());
        }

        SingPassLog::info('ID token claims verified');
    }
}
