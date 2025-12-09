<?php

namespace Accredifysg\SingPassLogin;

use Accredifysg\SingPassLogin\Events\MyInfoDataRetrievedEvent;
use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassTokenServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetUserInfoServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassLoginInterface;
use Accredifysg\SingPassLogin\Models\SingPassUser;
use Exception;

readonly class SingPassLogin implements SingPassLoginInterface
{
    public function __construct(
        private OpenIdDiscoveryServiceInterface $openIdDiscoveryService,
        private GetSingPassTokenServiceInterface $getSingPassTokenService,
        private SingPassJwtServiceInterface $singPassJwtService,
        private GetSingPassJwksServiceInterface $getSingPassJwksService,
        private GetUserInfoServiceInterface $getUserInfoService
    ) {}

    public function handleCallback(string $code, string $state, string $codeVerifier): void
    {
        $this->openIdDiscoveryService->cacheOpenIdDiscovery();
        $tokenResponseDto = $this->getSingPassTokenService->getToken($code, $codeVerifier, $state);

        // Check if MyInfo data should be retrieved
        if ($tokenResponseDto->hasAccessToken() && $tokenResponseDto->accessToken !== null
            && $this->getUserInfoService->shouldCallUserInfo($tokenResponseDto->accessToken)) {
            // Retrieve MyInfo data and emit MyInfo event
            $myInfoData = $this->getUserInfoService->getUserInfo($tokenResponseDto->accessToken);

            if ($myInfoData) {
                event(new MyInfoDataRetrievedEvent($myInfoData, $state));
            }
        } else {
            // Authentication only - emit login event
            $jwtToken = $this->singPassJwtService->jweDecrypt($tokenResponseDto->idToken);
            $jwksKeyset = $this->getSingPassJwksService->getSingPassJwks();
            $payload = $this->singPassJwtService->jwtDecode($jwtToken, $jwksKeyset);
            $this->singPassJwtService->verifyPayload($payload);
            $singPassUser = $this->getSingPassUser($payload);
            event(new SingPassSuccessfulLoginEvent($singPassUser, $state));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function getSingPassUser(array $payload): SingPassUser
    {
        // Get NRIC and UUID
        $sub = $payload['sub'];
        if ($sub === '') {
            throw new JwtPayloadException(400, 'Sub is empty');
        }

        $subParts = explode(',', $sub);

        try {
            $nric = substr($subParts[0], 2);
            $uuid = substr($subParts[1], 2);
        } catch (Exception) {
            throw new JwtPayloadException(400, 'Cannot get IC and UUID');
        }

        if ($nric === '' || $uuid === '') {
            throw new JwtPayloadException(400, 'NRIC or UUID is empty');
        }

        return new SingPassUser($uuid, $nric);
    }
}
