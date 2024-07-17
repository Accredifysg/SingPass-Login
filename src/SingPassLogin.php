<?php

namespace Accredifysg\SingPassLogin;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Models\SingPassUser;
use Accredifysg\SingPassLogin\Services\GetSingPassJwksService;
use Accredifysg\SingPassLogin\Services\getSingPassTokenService;
use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;
use Exception;

readonly class SingPassLogin
{
    public function __construct(private string $code, private string $state) {}

    public function handleCallback(): void
    {
        OpenIdDiscoveryService::cacheOpenIdDiscovery();
        $jweToken = getSingPassTokenService::getToken($this->code);
        $jwtToken = SingPassJwtService::jweDecrypt($jweToken);
        $jwksKeyset = GetSingPassJwksService::getSingPassJwks();
        $payload = SingPassJwtService::jwtDecode($jwtToken, $jwksKeyset);
        SingPassJwtService::verifyPayload($payload);
        $singPassUser = $this->getSingPassUser($payload);

        event(new SingPassSuccessfulLoginEvent($singPassUser));
    }

    private function getSingPassUser($payload): SingPassUser
    {
        // Get NRIC and UUID
        $sub = $payload->sub;
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
