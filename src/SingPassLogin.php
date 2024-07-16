<?php

namespace Accredifysg\SingPassLogin;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Models\SingPassUser;
use Accredifysg\SingPassLogin\Services\getSingPassJwksService;
use Accredifysg\SingPassLogin\Services\getSingPassTokenService;
use Accredifysg\SingPassLogin\Services\OpenIdDiscoveryService;
use Accredifysg\SingPassLogin\Services\SingPassJwtService;

class SingPassLogin
{
    public function __construct(private string $code, private string $state) {}

    public function handleCallback(): void
    {
        OpenIdDiscoveryService::cacheOpenIdDiscovery();
        $jweToken = getSingPassTokenService::getToken($this->code);
        $jwtToken = SingPassJwtService::jweDecrypt($jweToken);
        $jwksKeyset = getSingPassJwksService::getSingPassJwks();
        $payload = SingPassJwtService::jwtDecode($jwtToken, $jwksKeyset);
        SingPassJwtService::verifyPayload($payload);
        $singPassUser = $this->getSingPassUser($payload);

        event(new SingPassSuccessfulLoginEvent($singPassUser));
    }

    private function getSingPassUser(string $payload): SingPassUser
    {
        // Get NRIC and UUID
        $sub = $payload->sub;
        $subParts = explode(',', $sub);
        $nric = substr($subParts[0], 2);
        $uuid = substr($subParts[1], 2);
        if ($nric === '' || $uuid === '') {
            throw new JwtPayloadException(400, 'Cannot get IC and UUID');
        }

        return new SingPassUser($uuid, $nric);
    }
}
