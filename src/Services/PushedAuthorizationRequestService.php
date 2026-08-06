<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Services;

use Accredifysg\SingPassLogin\DTOs\OpenIdConfigurationDto;
use Accredifysg\SingPassLogin\Exceptions\PushedAuthorizationRequestException;
use Accredifysg\SingPassLogin\Interfaces\PushedAuthorizationRequestServiceInterface;
use Accredifysg\SingPassLogin\Support\SingPassLog;
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
    public function sendRequest(array $params, string $dpopProofJwt, string $cacheKey): string
    {
        $openIdConfig = OpenIdConfigurationDto::fromCache(Cache::get($cacheKey));

        if ($openIdConfig === null) {
            throw new PushedAuthorizationRequestException(
                statusCode: 500,
                message: 'OpenID configuration not found in cache',
            );
        }

        $parEndpoint = $openIdConfig->pushedAuthorizationRequestEndpoint;

        SingPassLog::info('PAR request', [
            'endpoint' => $parEndpoint,
            'params' => SingPassLog::redact($params),
        ]);

        $response = Http::asForm()
            ->withHeaders(['DPoP' => $dpopProofJwt])
            ->post($parEndpoint, $params);

        try {
            $responseData = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            SingPassLog::error('PAR response parse failure', [
                'endpoint' => $parEndpoint,
                'http_status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            $statusCode = $response->status() < 400 ? 502 : $response->status();

            throw new PushedAuthorizationRequestException(
                statusCode: $statusCode,
                message: 'Failed to parse PAR response',
            );
        }

        if (! is_object($responseData)) {
            throw new PushedAuthorizationRequestException(
                statusCode: 500,
                message: 'PAR response JSON must be an object',
            );
        }

        if ($response->failed() || isset($responseData->error)) {
            SingPassLog::error('PAR request failed', [
                'endpoint' => $parEndpoint,
                'http_status' => $response->status(),
                'response_body' => $response->body(),
                'params_sent' => SingPassLog::redact($params),
            ]);

            $errorCodeRaw = $responseData->error ?? 'server_error';
            $errorCode = is_string($errorCodeRaw) ? $errorCodeRaw : 'server_error';
            $errorDescription = null;
            if (isset($responseData->error_description)) {
                $ed = $responseData->error_description;
                $errorDescription = is_string($ed) ? $ed : null;
            }

            throw new PushedAuthorizationRequestException(
                statusCode: $response->status(),
                message: 'Pushed Authorization Request failed',
                errorCode: $errorCode,
                errorDescription: $errorDescription,
            );
        }

        if (! isset($responseData->request_uri)) {
            SingPassLog::error('PAR response missing request_uri', [
                'endpoint' => $parEndpoint,
                'response_body' => $response->body(),
            ]);

            throw new PushedAuthorizationRequestException(
                statusCode: 500,
                message: 'PAR response missing request_uri',
            );
        }

        $requestUri = $responseData->request_uri;
        if (! is_string($requestUri)) {
            throw new PushedAuthorizationRequestException(
                statusCode: 500,
                message: 'PAR response request_uri must be a string',
            );
        }

        SingPassLog::info('PAR request successful', [
            'request_uri' => $requestUri,
        ]);

        return $requestUri;
    }
}
