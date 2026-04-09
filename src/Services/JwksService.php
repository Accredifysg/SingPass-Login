<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\JwksException;
use Accredifysg\SingPassLogin\Interfaces\JwksServiceInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\JWKSet;

final class JwksService implements JwksServiceInterface
{
    /**
     * @throws JwksException
     */
    public function getJwks(string $cacheKey): JWKSet
    {
        $openIdConfig = Cache::get($cacheKey);

        if (! $openIdConfig instanceof OpenIdConfigurationDto) {
            throw new JwksException(500, 'OpenID configuration not found in cache');
        }

        try {
            $response = Http::get($openIdConfig->jwksUri)->throwUnlessStatus(200)->body();

            return JWKSet::createFromJson($response);
        } catch (Exception) {
            throw new JwksException;
        }
    }
}
