<?php

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\PushedAuthorizationRequestException;
use Accredifysg\SingPassLogin\Interfaces\PushedAuthorizationRequestServiceInterface;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class PushedAuthorizationRequestService implements PushedAuthorizationRequestServiceInterface
{
    /**
     * @param  array<string, string>  $params
     *
     * @throws ConnectionException
     * @throws PushedAuthorizationRequestException
     */
    public function sendRequest(array $params, string $dpopProofJwt): string
    {
        /** @var OpenIdConfigurationDto $openIdConfig */
        $openIdConfig = Cache::get('openId');
        $parEndpoint = $openIdConfig->pushedAuthorizationRequestEndpoint;

        $response = Http::bodyFormat('form_params')
            ->contentType('application/x-www-form-urlencoded; charset=ISO-8859-1')
            ->withHeaders(['DPoP' => $dpopProofJwt])
            ->post($parEndpoint, $params);

        try {
            $responseData = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            throw new PushedAuthorizationRequestException(
                statusCode: $response->status(),
                message: 'Failed to parse PAR response',
            );
        }

        if ($response->failed() || isset($responseData->error)) {
            throw new PushedAuthorizationRequestException(
                statusCode: $response->status(),
                message: 'Pushed Authorization Request failed',
                errorCode: $responseData->error ?? 'server_error',
                errorDescription: $responseData->error_description ?? null,
            );
        }

        if (! isset($responseData->request_uri)) {
            throw new PushedAuthorizationRequestException(
                statusCode: 500,
                message: 'PAR response missing request_uri',
            );
        }

        return $responseData->request_uri;
    }
}
