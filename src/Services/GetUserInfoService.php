<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\UserInfoDecryptionException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoRequestException;
use Accredifysg\SingPassLogin\Exceptions\UserInfoVerificationException;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetUserInfoServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWK;

final readonly class GetUserInfoService implements GetUserInfoServiceInterface
{
    public function __construct(
        private SingPassJwtServiceInterface $singPassJwtService,
        private GetSingPassJwksServiceInterface $getSingPassJwksService,
        private DPoPServiceInterface $dpopService
    ) {}

    /**
     * Determine if the UserInfo endpoint should be called based on access token scopes.
     * Returns true only when the access token contains MyInfo scopes (scopes that are
     * NOT login scopes). Login scopes like user.identity, name, email, mobileno are
     * returned in the ID token and do not require a UserInfo call.
     *
     * @param  string  $accessToken  The access token JWT to decode
     * @return bool True if UserInfo should be called, false otherwise
     */
    public function shouldCallUserInfo(string $accessToken): bool
    {
        $scopes = $this->extractScopesFromAccessToken($accessToken);

        $loginScopes = config('singpass-login.login_scopes', [
            'openid',
            'user.identity',
            'name',
            'email',
            'mobileno',
        ]);

        foreach ($scopes as $scope) {
            if (! in_array($scope, $loginScopes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract scopes from the access token JWT
     *
     * @param  string  $accessToken  The access token JWT
     * @return array<int, string>
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
     * @param  JWK  $dpopKey  The DPoP private key for proof generation
     * @return array<string, mixed>|null The user info data as an associative array, or null if not applicable
     *
     * @throws ConnectionException
     */
    public function getUserInfo(string $accessToken, JWK $dpopKey): ?array
    {
        // Check if UserInfo should be called based on access token scopes
        if (! $this->shouldCallUserInfo($accessToken)) {
            return null;
        }

        /** @var OpenIdConfigurationDto $openIdConfig */
        $openIdConfig = Cache::get('openId');
        $userinfoEndpoint = $openIdConfig->userinfoEndpoint;

        // Generate DPoP proof JWT with access token hash
        $ath = $this->dpopService->computeAccessTokenHash($accessToken);
        $dpopProofJwt = $this->dpopService->generateProofJwt($dpopKey, 'GET', $userinfoEndpoint, $ath);

        $response = Http::withHeaders([
            'Authorization' => "DPoP {$accessToken}",
            'DPoP' => $dpopProofJwt,
        ])->get($userinfoEndpoint);

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

        // Extract person_info from the response (FAPI 2.0 nesting)
        return $payload['person_info'] ?? $payload;
    }
}
