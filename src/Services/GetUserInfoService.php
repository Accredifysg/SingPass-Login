<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetUserInfoServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final readonly class GetUserInfoService implements GetUserInfoServiceInterface
{
    public function __construct(
        private SingPassJwtServiceInterface $singPassJwtService,
        private GetSingPassJwksServiceInterface $getSingPassJwksService
    ) {}

    /**
     * Determine if the UserInfo endpoint should be called based on access token scopes
     *
     * @param  string  $accessToken  The access token JWT to decode
     * @return bool True if UserInfo should be called, false otherwise
     */
    public function shouldCallUserInfo(string $accessToken): bool
    {
        // Decode the access token to extract scopes
        $scopes = $this->extractScopesFromAccessToken($accessToken);

        // UserInfo should be called if there are scopes beyond just 'openid'
        return count($scopes) > 1 || (count($scopes) === 1 && ! in_array('openid', $scopes));
    }

    /**
     * Extract scopes from the access token JWT
     *
     * @param  string  $accessToken  The access token JWT
     * @return array<int, string> Array of scopes
     */
    private function extractScopesFromAccessToken(string $accessToken): array
    {
        try {
            // Decode the JWT without verification (we just need to read the payload)
            // The access token is a JWT in the format: header.payload.signature
            $parts = explode('.', $accessToken);

            if (count($parts) !== 3) {
                return ['openid'];
            }

            // Decode the payload (second part)
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (! isset($payload['scope'])) {
                return ['openid'];
            }

            // Scopes are space-separated in the JWT
            return explode(' ', $payload['scope']);
        } catch (Exception) {
            // If we can't decode, default to openid only
            return ['openid'];
        }
    }

    /**
     * Retrieve user information from the UserInfo endpoint
     *
     * @param  string  $accessToken  The access token to use for authentication
     * @return array<string, mixed>|null The user info data as an associative array, or null if not applicable
     *
     * @throws ConnectionException
     */
    public function getUserInfo(string $accessToken): ?array
    {
        // Check if UserInfo should be called based on access token scopes
        if (! $this->shouldCallUserInfo($accessToken)) {
            return null;
        }

        // Get userinfo_endpoint from cached OpenID discovery
        $openIdConfig = Cache::get('openId');

        if (! $openIdConfig || ! isset($openIdConfig->userinfo_endpoint)) {
            throw new UserInfoRequestException(500, 'UserInfo endpoint not found in OpenID discovery.');
        }

        $userinfoEndpoint = $openIdConfig->userinfo_endpoint;

        // Make HTTP GET request with Bearer token authorization
        $response = Http::withToken($accessToken)->get($userinfoEndpoint);

        if ($response->failed()) {
            throw new UserInfoRequestException(
                $response->status(),
                "UserInfo endpoint request failed with status {$response->status()}: {$userinfoEndpoint}"
            );
        }

        try {
            // Decrypt JWE response using existing SingPassJwtService
            $jwtToken = $this->singPassJwtService->jweDecrypt($response->body());
        } catch (Exception $e) {
            throw new UserInfoDecryptionException(
                500,
                "Failed to decrypt UserInfo JWE token: {$e->getMessage()}",
                $e
            );
        }

        try {
            // Verify and decode JWT using existing SingPassJwtService
            $jwksKeyset = $this->getSingPassJwksService->getSingPassJwks();
            $payload = $this->singPassJwtService->jwtDecode($jwtToken, $jwksKeyset);
        } catch (Exception $e) {
            throw new UserInfoVerificationException(
                500,
                "Failed to verify UserInfo JWT token: {$e->getMessage()}",
                $e
            );
        }

        // Return payload as associative array
        return $payload;
    }
}
