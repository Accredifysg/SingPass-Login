<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\JwksException;
use Accredifysg\SingPassLogin\Interfaces\JwksServiceInterface;
use Accredifysg\SingPassLogin\Support\SingPassLog;
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

        SingPassLog::info('JWKS request', ['uri' => $openIdConfig->jwksUri]);

        try {
            $response = Http::createPendingRequest()
                ->get($openIdConfig->jwksUri)
                ->throwUnlessStatus(200)
                ->body();

            SingPassLog::info('JWKS fetched successfully', ['uri' => $openIdConfig->jwksUri]);

            return JWKSet::createFromJson($response);
        } catch (Exception) {
            SingPassLog::error('JWKS request failed', ['uri' => $openIdConfig->jwksUri]);

            throw new JwksException;
        }
    }
}
