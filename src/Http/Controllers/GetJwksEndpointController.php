<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;

class GetJwksEndpointController extends Controller
{
    /**
     * Returns the JSON JWKS located in the storage folder
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            return response()->json(json_decode(File::get(storage_path('jwks/jwks.json')), true, 512, JSON_THROW_ON_ERROR));
        } catch (Exception) {
            throw new JwksInvalidException;
        }
    }
}
