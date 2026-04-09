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
use Jose\Component\Core\JWK;

readonly class SingPassLogin implements SingPassLoginInterface
{
    public function __construct(
        private OpenIdDiscoveryServiceInterface $openIdDiscoveryService,
        private GetSingPassTokenServiceInterface $getSingPassTokenService,
        private SingPassJwtServiceInterface $singPassJwtService,
        private GetSingPassJwksServiceInterface $getSingPassJwksService,
        private GetUserInfoServiceInterface $getUserInfoService
    ) {}

    public function handleCallback(string $code, string $state, string $codeVerifier, JWK $dpopKey, string $clientId, string $redirectUri): void
    {
        $this->openIdDiscoveryService->cacheOpenIdDiscovery();
        $tokenResponseDto = $this->getSingPassTokenService->getToken($code, $codeVerifier, $dpopKey, $clientId, $redirectUri);

        if ($tokenResponseDto->hasAccessToken() && $tokenResponseDto->accessToken !== null
            && $this->getUserInfoService->shouldCallUserInfo($tokenResponseDto->accessToken)) {
            $myInfoData = $this->getUserInfoService->getUserInfo($tokenResponseDto->accessToken, $dpopKey);

            if ($myInfoData) {
                event(new MyInfoDataRetrievedEvent($myInfoData, $state));
            }
        } else {
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
        $sub = $payload['sub'] ?? '';
        if ($sub === '') {
            throw new JwtPayloadException(400, 'Sub is empty');
        }

        $uuid = $sub;
        $subAttributes = $payload['sub_attributes'] ?? [];

        return new SingPassUser(
            uuid: $uuid,
            nric: $subAttributes['identity_number'] ?? null,
            accountType: $subAttributes['account_type'] ?? null,
            identityCoi: $subAttributes['identity_coi'] ?? null,
            name: $subAttributes['name'] ?? null,
            email: $subAttributes['email'] ?? null,
            mobileNo: $subAttributes['mobileno'] ?? null,
        );
    }
}
