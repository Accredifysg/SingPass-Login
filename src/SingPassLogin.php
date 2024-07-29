<?php

namespace Accredifysg\SingPassLogin;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassTokenServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Accredifysg\SingPassLogin\Models\SingPassUser;
use Exception;

readonly class SingPassLogin
{
    public function __construct(
        private string $code,
        private string $state,
        private OpenIdDiscoveryServiceInterface $openIdDiscoveryService,
        private GetSingPassTokenServiceInterface $getSingPassTokenService,
        private SingPassJwtServiceInterface $singPassJwtService,
        private GetSingPassJwksServiceInterface $getSingPassJwksService
    ) {}

    public function handleCallback(): void
    {
        $this->openIdDiscoveryService->cacheOpenIdDiscovery();
        $jweToken = $this->getSingPassTokenService->getToken($this->code);
        $jwtToken = $this->singPassJwtService->jweDecrypt($jweToken);
        $jwksKeyset = $this->getSingPassJwksService->getSingPassJwks();
        $payload = $this->singPassJwtService->jwtDecode($jwtToken, $jwksKeyset);
        $this->singPassJwtService->verifyPayload($payload);
        $singPassUser = $this->getSingPassUser($payload);

        event(new SingPassSuccessfulLoginEvent($singPassUser));
    }

    private function getSingPassUser($payload): SingPassUser
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
