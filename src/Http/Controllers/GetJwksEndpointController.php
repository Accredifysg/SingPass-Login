<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JsonException;

class GetJwksEndpointController extends Controller
{
    /**
     * Returns the JSON JWKS located in the storage folder
     *
     * @throws JsonException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $jwks = config('services.singpass-login.jwks');

        if ($jwks !== null) {

            try {
                return response()->json(json_decode($jwks, true, 512, JSON_THROW_ON_ERROR));
            } catch (JsonException) {
                throw new JwksInvalidException(500, 'JWKS is an invalid JSON string.');
            }
        } else {
            throw new JwksInvalidException(500, 'JWKS environment variable not set.');
        }
    }
}
