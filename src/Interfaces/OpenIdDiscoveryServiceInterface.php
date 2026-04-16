<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Interfaces;

interface OpenIdDiscoveryServiceInterface
{
    public function cacheOpenIdDiscovery(string $discoveryEndpoint, string $cacheKey): void;
}
