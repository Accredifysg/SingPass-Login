<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Exceptions\JweDecryptionFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwtDecodeFailedException;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256CBCHS512;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHESA256KW;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\Compression\Deflate;
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

final class SingPassJwtService
{
    /**
     * Gets the key to sign the Assertion with based on what is set in the ENV
     *
     * @return JWK|JWKSet
     *
     * @throws FileNotFoundException
     */
    public static function getSigningJwk(): JWK|JWKSet
    {
        $jwkSets = JWKSet::createFromJson(File::get(storage_path('jwks/jwks.json')));
        $signingKey = $jwkSets->get(config('services.singpass-login.signingKid'));
        $signingKeyArray = $signingKey->all();
        $signingKeyArray['d'] = config('services.singpass-login.privateExponent');
        return JWKFactory::createFromValues($signingKeyArray);
    }

    /**
     * Generate the client assertion needed to retrieve a token to use for subsequent calls
     *
     * @param $jwk
     *
     * @return string
     */
    public static function generateClientAssertion($jwk): string
    {
        $algorithmManager = new AlgorithmManager([
            new ES512(),
        ]);

        $jwsBuilder = new JWSBuilder($algorithmManager);

        $payload = json_encode([
            'sub' => config('services.singpass-login.clientId'),
            'aud' => Cache::get('openId')->issuer,
            'iss' => config('services.singpass-login.clientId'),
            'iat' => time(),
            'exp' => time() + 119,
        ]);

        $jws = $jwsBuilder->create()
            ->withPayload($payload)
            ->addSignature($jwk, [
                'typ' => 'JWT',
                'alg' => 'ES512',
                'kid' => config('services.singpass-login.signingKid'),
            ])->build();

        $serializer = new JwsCompactSerializer();

        return $serializer->serialize($jws, 0);
    }

    /**
     * Decrypts the JWE that was returned from SingPass's token endpoint
     *
     * @param $token
     *
     * @return string|null
     *
     * @throws JweDecryptionFailedException
     */
    public static function jweDecrypt($token): ?string
    {
        $keyEncryptionAlgorithmManager = new AlgorithmManager([
            new ECDHESA256KW(),
        ]);

        $contentEncryptionAlgorithmManager = new AlgorithmManager([
            new A256CBCHS512(),
        ]);

        $compressionMethodManager = new CompressionMethodManager([
            new Deflate(),
        ]);

        $jweDecrypter = new JWEDecrypter($keyEncryptionAlgorithmManager, $contentEncryptionAlgorithmManager, $compressionMethodManager);

        $privateKey = str_replace('\\n', "\n", config('services.singpass-login.encryption_key'));
        $key = JWKFactory::createFromKey($privateKey);

        $serializerManager = new JWESerializerManager([
            new CompactSerializer(),
        ]);

        $jwe = $serializerManager->unserialize($token);

        if ($jweDecrypter->decryptUsingKey($jwe, $key, 0)) {
            $headerCheckerManager = new HeaderCheckerManager([
                new AlgorithmChecker(['ECDH-ES+A256KW']),
            ], [
                new JWETokenSupport(),
            ]);

            $jweLoader = new JWELoader($serializerManager, $jweDecrypter, $headerCheckerManager);

            $jwe = $jweLoader->loadAndDecryptWithKey($token, $key, $recipient);

            return $jwe->getPayload();
        }

        throw new JweDecryptionFailedException;
    }

    /**
     * Decrypts the JWT that was encrypted within the JWE token
     *
     * @param $token
     * @param $keySet
     *
     * @return mixed
     *
     * @throws JwtDecodeFailedException
     */
    public static function jwtDecode($token, $keySet): mixed
    {
        $algorithmManager = new AlgorithmManager([
            new ES256(),
        ]);

        $jwsVerifier = new JWSVerifier($algorithmManager);

        $serializerManager = new JWSSerializerManager([
            new JwsCompactSerializer(),
        ]);

        $kid = $serializerManager->unserialize($token)->getSignature(0)->getProtectedHeaderParameter('kid');
        $key = JWKFactory::createFromKeySet($keySet, $kid);

        $headerCheckerManager = new HeaderCheckerManager([
            new AlgorithmChecker(['ES256']),
        ], [
            new JWSTokenSupport(),
        ]);

        $jwsLoader = new JWSLoader($serializerManager, $jwsVerifier, $headerCheckerManager);

        $jws = $jwsLoader->loadAndVerifyWithKey($token, $key, $signature);

        try {
            return json_decode($jws->getPayload(), false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            throw new JwtDecodeFailedException;
        }
    }

    /**
     * Verifies they payload to ensure it is valid
     *
     * @param string $payload
     *
     * @return void
     *
     * @throws JwtPayloadException
     */
    public static function verifyPayload(string $payload)
    {
        // Check if token has expired
        $iat = $payload->iat;
        $exp = $payload->exp;
        $now = Carbon::now()->timestamp;
        if ($iat > $now || $exp < $now) {
            throw new JwtPayloadException(400,'Token times are invalid');
        }

        // Check if the client_id of the relaying party is SingPass
        $aud = $payload->aud;
        $singpassClientId = config('services.singpass-login.clientId');
        if ($aud !== $singpassClientId) {
            throw new JwtPayloadException(400, 'Wrong client ID');
        }

        // Check if the principal is SingPass
        $iss = $payload->iss;
        $singpassDomain = config('services.singpass-login.domain');
        if ($iss !== $singpassDomain) {
            throw new JwtPayloadException(400,'Came from wrong principal');
        }
    }
}