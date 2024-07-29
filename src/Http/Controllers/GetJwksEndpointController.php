<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

class GetJwksEndpointController extends Controller
{
    /**
     * Returns the JSON JWKS located in the storage folder
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            echo (File::get(storage_path('jwks/jwks.json')));
            return response()->json(json_decode(File::get(storage_path('jwks/jwks.json')), true, 512, JSON_THROW_ON_ERROR));
        } catch (Exception) {
            throw new JwksInvalidException;
        }
    }
}
