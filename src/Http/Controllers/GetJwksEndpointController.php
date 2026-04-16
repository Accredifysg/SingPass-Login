<?php

declare(strict_types=1);

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
     */
    public function __invoke(Request $request): JsonResponse
    {
        $jwks = config('ndi.jwks');

        if ($jwks !== null) {
            if (! is_string($jwks)) {
                throw new JwksInvalidException(500, 'JWKS configuration must be a JSON string.');
            }

            try {
                $decoded = json_decode($jwks, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new JwksInvalidException(500, 'JWKS is an invalid JSON string.');
            }

            if (! is_array($decoded)) {
                throw new JwksInvalidException(500, 'JWKS JSON must decode to an array or object.');
            }

            return response()->json($decoded);
        } else {
            throw new JwksInvalidException(500, 'JWKS environment variable not set.');
        }
    }
}
