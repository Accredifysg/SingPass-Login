<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetUserInfoServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\JwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\JwtServiceInterface;
use Accredifysg\SingPassLogin\Support\SingPassLog;
use Accredifysg\SingPassLogin\Support\TypeNarrow;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWK;

final readonly class GetUserInfoService implements GetUserInfoServiceInterface
{
    public function __construct(
        private JwtServiceInterface $jwtService,
        private JwksServiceInterface $jwksService,
        private DPoPServiceInterface $dpopService
    ) {}

    /**
     * @param  array<int, string>  $loginScopes
     */
    public function shouldCallUserInfo(string $accessToken, array $loginScopes): bool
    {
        $scopes = $this->extractScopesFromAccessToken($accessToken);

        foreach ($scopes as $scope) {
            if (! in_array($scope, $loginScopes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     *
     * @throws UserInfoRequestException
     */
    private function extractScopesFromAccessToken(string $accessToken): array
    {
        $parts = explode('.', $accessToken);

        if (count($parts) !== 3) {
            throw new UserInfoRequestException(500, 'Access token is not a valid JWT (expected 3 parts).');
        }

        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payloadJson === false) {
            throw new UserInfoRequestException(500, 'Access token payload could not be base64-decoded.');
        }

        $payload = json_decode($payloadJson, true);
        if (! is_array($payload) || ! isset($payload['scope'])) {
            throw new UserInfoRequestException(500, 'Access token payload does not contain a scope claim.');
        }

        $scope = $payload['scope'];
        if (! is_string($scope)) {
            throw new UserInfoRequestException(500, 'Access token scope claim is not a string.');
        }

        return explode(' ', $scope);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function getUserInfo(string $accessToken, JWK $dpopKey, string $cacheKey): array
    {
        $openIdConfig = Cache::get($cacheKey);

        if (! $openIdConfig instanceof OpenIdConfigurationDto) {
            throw new UserInfoRequestException(500, 'OpenID configuration not found in cache');
        }

        $userinfoEndpoint = $openIdConfig->userinfoEndpoint;

        SingPassLog::info('UserInfo request', ['endpoint' => $userinfoEndpoint]);

        $ath = $this->dpopService->computeAccessTokenHash($accessToken);
        $dpopProofJwt = $this->dpopService->generateProofJwt($dpopKey, 'GET', $userinfoEndpoint, $ath);

        $response = Http::withHeaders([
            'Authorization' => "DPoP {$accessToken}",
            'DPoP' => $dpopProofJwt,
        ])->get($userinfoEndpoint);

        if ($response->failed()) {
            SingPassLog::error('UserInfo request failed', [
                'endpoint' => $userinfoEndpoint,
                'http_status' => $response->status(),
            ]);

            throw new UserInfoRequestException(
                $response->status(),
                "UserInfo endpoint request failed with status {$response->status()}: {$userinfoEndpoint}"
            );
        }

        SingPassLog::info('UserInfo response received, decrypting JWE', [
            'endpoint' => $userinfoEndpoint,
        ]);

        try {
            $jwtToken = $this->jwtService->jweDecrypt($response->body());
        } catch (Exception $e) {
            SingPassLog::error('UserInfo JWE decryption failed', [
                'endpoint' => $userinfoEndpoint,
                'error' => $e->getMessage(),
            ]);

            throw new UserInfoDecryptionException(
                500,
                "Failed to decrypt UserInfo JWE token: {$e->getMessage()}",
                $e
            );
        }

        try {
            $jwksKeyset = $this->jwksService->getJwks($cacheKey);
            $payload = $this->jwtService->jwtDecode($jwtToken, $jwksKeyset);
        } catch (Exception $e) {
            SingPassLog::error('UserInfo JWT verification failed', [
                'endpoint' => $userinfoEndpoint,
                'error' => $e->getMessage(),
            ]);

            throw new UserInfoVerificationException(
                500,
                "Failed to verify UserInfo JWT token: {$e->getMessage()}",
                $e
            );
        }

        SingPassLog::info('UserInfo data retrieved successfully');

        $result = $payload['person_info'] ?? $payload;
        if (! is_array($result)) {
            throw new UserInfoVerificationException(500, 'UserInfo payload must be a JSON object.');
        }

        return TypeNarrow::stringKeyedArray($result)
            ?? throw new UserInfoVerificationException(500, 'UserInfo payload keys must be strings.');
    }
}
