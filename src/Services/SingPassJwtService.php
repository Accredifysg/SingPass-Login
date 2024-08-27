<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Exceptions\JweDecryptionFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Exceptions\JwtDecodeFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\HeaderCheckerManager;
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

final class SingPassJwtService implements SingPassJwtServiceInterface
{
    /**
     * Gets the key to sign the Assertion with based on what is set in the ENV
     */
    public static function getSigningJwk(): JWK|JWKSet
    {
        try {
            $jwks = File::get(storage_path('jwks/jwks.json'));
        } catch (Exception) {
            throw new JwksInvalidException(500, 'JWKS JSON file could not be retrieved.');
        }

        try {
            $jwkSets = JWKSet::createFromJson($jwks);
        } catch (Exception) {
            throw new JwksInvalidException(500, 'JWKS JSON Invalid.');
        }

        try {
            $signingKey = $jwkSets->get(config('services.singpass-login.signing_kid'));
        } catch (Exception) {
            throw new JwksInvalidException(500, 'Signing key not found.');
        }

        if (config('services.singpass-login.private_exponent') === null) {
            throw new JwksInvalidException(500, 'Private exponent not set.');
        }
        $signingKeyArray = $signingKey->all();
        $signingKeyArray['d'] = config('services.singpass-login.private_exponent');

        return JWKFactory::createFromValues($signingKeyArray);
    }

    /**
     * Generate the client assertion needed to retrieve a token to use for subsequent calls
     */
    public static function generateClientAssertion($jwk): string
    {
        $algorithmManager = new AlgorithmManager([
            new ES512,
        ]);

        $jwsBuilder = new JWSBuilder($algorithmManager);

        $payload = json_encode([
            'sub' => config('services.singpass-login.clientId'),
            'aud' => Cache::get('openId')->issuer,
            'iss' => config('services.singpass-login.clientId'),
            'iat' => time(),
            'exp' => time() + 119,
        ]);

        try {
            $jws = $jwsBuilder->create()
                ->withPayload($payload)
                ->addSignature($jwk, [
                    'typ' => 'JWT',
                    'alg' => 'ES512',
                    'kid' => config('services.singpass-login.signingKid'),
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

        $jweDecrypter = new JWEDecrypter($algorithmManager);

        try {
            $privateKey = str_replace('\\n', "\n", config('services.singpass-login.encryption_key'));
            $key = JWKFactory::createFromKey($privateKey);
        } catch (InvalidArgumentException) {
            throw new JweDecryptionFailedException(500, 'Private key could not be decrypted.');
        }

        $serializerManager = new JWESerializerManager([
            new CompactSerializer,
        ]);

        $jwe = $serializerManager->unserialize($jweToken);

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

        throw new JweDecryptionFailedException;
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
        // Check if token has expired
        $iat = $payload['iat'];
        $exp = $payload['exp'];
        $now = Carbon::now()->timestamp;
        if ($iat > $now || $exp < $now) {
            throw new JwtPayloadException(400, 'Token times are invalid');
        }

        // Check if the client_id of the relaying party is SingPass
        $aud = $payload['aud'];
        $singpassClientId = config('services.singpass-login.clientId');
        if ($aud !== $singpassClientId) {
            throw new JwtPayloadException(400, 'Wrong client ID');
        }

        // Check if the principal is SingPass
        $iss = $payload['iss'];
        $singpassDomain = config('services.singpass-login.domain');
        if ($iss !== $singpassDomain) {
            throw new JwtPayloadException(400, 'Came from wrong principal');
        }
    }
}
